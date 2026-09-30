<?php

namespace App\Livewire\Sale;

use App\Models\Client;
use App\Models\Sale;
use App\Models\SunatDocument;
use App\Services\Billing\BillingService;
use App\Services\Core\AccountRule;
use App\Services\Sale\SaleService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Detalle de una venta. Solo permite editar datos que no afectan stock
 * (cliente, fecha, comprobante, notas); para corregir productos o cantidades
 * se anula la venta y se vuelve a registrar.
 */
class SaleDetailModal extends Component
{
    public ?int $saleId = null;

    public bool $isOpen = false;

    public ?int $client_id = null;

    public ?string $client_name = null;

    public ?string $sale_date = null;

    public ?string $notes = null;

    public string $document_type = 'nota_venta';

    public ?string $document_number = null;

    public ?string $invoice_date = null;

    public bool $has_invoice_pending = false;

    public string $cancellationReason = '';

    // Remonta el buscador de cliente para que muestre el recién creado.
    public int $clientPickerVersion = 0;

    #[On('open-sale-detail')]
    public function loadSale(int $id): void
    {
        $this->resetValidation();
        $this->reset();

        $this->saleId = $id;
        unset($this->sale, $this->liveDocument);

        $sale = $this->sale;

        $this->client_id = $sale->client_id;
        $this->client_name = $sale->client?->display_name;
        $this->sale_date = $sale->sale_date?->format('Y-m-d');
        $this->notes = $sale->notes;
        $this->document_type = $sale->document_type;
        $this->document_number = $sale->document_number;
        $this->invoice_date = ($sale->invoice_date ?? now())->format('Y-m-d');
        $this->has_invoice_pending = $sale->has_invoice_pending;

        $this->isOpen = true;
    }

    #[Computed]
    public function sale(): ?Sale
    {
        if (! $this->saleId) {
            return null;
        }

        return Sale::with([
            'client:id,display_name,document_type,document_number',
            'user:id,name',
            'cancelledBy:id,name',
            'details.variant.product:id,name,code',
            'details.variant.size:id,name',
            'details.variant.color:id,name,hex_code',
            'details.physicalProduct:id,code',
        ])->findOrFail($this->saleId);
    }

    #[On('entity-selected')]
    public function handleEntitySelected(string $context, int $id, string $label): void
    {
        if ($context === 'sale-edit-client') {
            $this->changeClient($id, $label);
        }
    }

    /**
     * Cliente creado con el botón "+" (ej. para facturar a una empresa que
     * aún no estaba registrada): queda asignado a la venta abierta.
     */
    #[On('client-created')]
    public function selectCreatedClient(int $clientId): void
    {
        $client = $this->isOpen ? Client::find($clientId) : null;

        if ($client) {
            $this->changeClient($client->id, $client->display_name);
            $this->clientPickerVersion++;
        }
    }

    #[On('entity-cleared')]
    public function handleEntityCleared(string $context): void
    {
        if ($context === 'sale-edit-client') {
            $this->changeClient(null, null);
        }
    }

    /**
     * El cliente se guarda en la venta apenas se elige, sin esperar al botón
     * "Guardar": así "Facturar electrónicamente" siempre usa el cliente que se ve.
     * Con un comprobante electrónico ya emitido no se cambia (debe coincidir con SUNAT).
     */
    protected function changeClient(?int $clientId, ?string $clientName): void
    {
        if (! $this->isOpen || ! $this->sale) {
            return;
        }

        $this->authorize('ventas.editar');
        $this->ensureEditable();

        if ($this->liveDocument) {
            $this->client_id = $this->sale->client_id;
            $this->client_name = $this->sale->client?->display_name;
            $this->clientPickerVersion++;
            Flux::toast(variant: 'danger', text: 'La venta ya tiene un comprobante electrónico: el cliente no se puede cambiar.');

            return;
        }

        $this->client_id = $clientId;
        $this->client_name = $clientName;
        $this->validateOnly('client_id', ['client_id' => ['nullable', 'integer', AccountRule::exists('clients')]]);

        $this->sale->update(['client_id' => $clientId]);
        unset($this->sale);

        $this->dispatch('saleUpdated');
        Flux::toast(variant: 'success', text: $clientName ? "Cliente de la venta: {$clientName}." : 'La venta quedó sin cliente.');
    }

    public function save(): void
    {
        $this->authorize('ventas.editar');
        $this->ensureEditable();

        $validated = $this->validate([
            'client_id' => ['nullable', 'integer', AccountRule::exists('clients')],
            'sale_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'document_type' => ['required', Rule::in(array_keys(Sale::DOCUMENT_TYPES))],
            'document_number' => ['nullable', 'string', 'max:50'],
        ]);

        // El comprobante electrónico manda: su tipo y número vienen de SUNAT, no se editan a mano.
        if ($this->liveDocument) {
            unset($validated['document_type'], $validated['document_number']);
        }

        $this->sale->update($validated);

        $this->afterChange('Venta actualizada correctamente.');
    }

    #[Computed]
    public function billingEnabled(): bool
    {
        return app(BillingService::class)->isEnabled();
    }

    #[Computed]
    public function liveDocument(): ?SunatDocument
    {
        return $this->sale?->liveSunatDocument()->first();
    }

    /**
     * Abre la vista previa de facturación electrónica de esta venta.
     */
    public function openInvoice(): void
    {
        $this->authorize('facturacion.emitir');
        $this->ensureEditable();

        $this->isOpen = false;
        $this->dispatch('open-invoice-sale', id: $this->saleId);
    }

    /**
     * Sin facturación electrónica configurada: "facturar" es registrar
     * los datos del comprobante emitido por fuera y quitar la marca de pendiente.
     */
    public function invoice(): void
    {
        $this->authorize('ventas.editar');
        $this->ensureEditable();
        abort_if($this->billingEnabled, 403, 'Con facturación electrónica activa, use "Facturar electrónicamente".');

        $validated = $this->validate([
            'document_type' => ['required', Rule::in(['boleta', 'factura'])],
            'document_number' => ['required', 'string', 'max:50'],
            'invoice_date' => ['required', 'date'],
        ], [
            'document_type.in' => 'Para facturar elige boleta o factura.',
        ]);

        $this->sale->update([
            ...$validated,
            'has_invoice_pending' => false,
        ]);

        $this->afterChange('Venta facturada correctamente.');
    }

    public function cancel(SaleService $service): void
    {
        $this->authorize('ventas.anular');
        $this->ensureEditable();

        $this->validate([
            'cancellationReason' => ['required', 'string', 'min:5', 'max:500'],
        ], [], ['cancellationReason' => 'motivo de anulación']);

        try {
            $service->cancel($this->sale, trim($this->cancellationReason));
        } catch (\RuntimeException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::modal('cancel-sale')->close();

        $pending = SunatDocument::where('sale_id', $this->saleId)->where('credit_note_required', true)->latest('id')->first();
        if ($pending) {
            // Sin notas de crédito automáticas aún: se avisa para emitirla por el comprobante que ya está en SUNAT.
            $this->isOpen = false;
            $this->dispatch('saleUpdated');
            Flux::toast(
                variant: 'warning',
                duration: 10000,
                text: "Venta {$this->sale->code} anulada y stock devuelto. Emita la nota de crédito por la {$pending->typeLabel()} {$pending->fullNumber()}.",
            );

            return;
        }

        $this->afterChange("Venta {$this->sale->code} anulada. El stock fue devuelto.");
    }

    protected function ensureEditable(): void
    {
        abort_if(! $this->sale || $this->sale->isCancelled(), 403, 'La venta está anulada.');
    }

    protected function afterChange(string $message): void
    {
        $this->isOpen = false;
        $this->dispatch('saleUpdated');
        Flux::toast(variant: 'success', text: $message);
    }

    public function render(): View
    {
        return view('livewire.sale.sale-detail-modal');
    }
}
