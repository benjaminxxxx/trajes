<?php

namespace App\Livewire\Sale;

use App\Models\Sale;
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

    #[On('open-sale-detail')]
    public function loadSale(int $id): void
    {
        $this->resetValidation();
        $this->reset();

        $this->saleId = $id;
        unset($this->sale);

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
            $this->client_id = $id;
            $this->client_name = $label;
        }
    }

    #[On('entity-cleared')]
    public function handleEntityCleared(string $context): void
    {
        if ($context === 'sale-edit-client') {
            $this->client_id = null;
            $this->client_name = null;
        }
    }

    public function save(): void
    {
        $this->authorize('ventas.editar');
        $this->ensureEditable();

        $validated = $this->validate([
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'sale_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'document_type' => ['required', Rule::in(array_keys(Sale::DOCUMENT_TYPES))],
            'document_number' => ['nullable', 'string', 'max:50'],
        ]);

        $this->sale->update($validated);

        $this->afterChange('Venta actualizada correctamente.');
    }

    /**
     * No hay integración con facturación electrónica: "facturar" es registrar
     * los datos del comprobante emitido y quitar la marca de pendiente.
     */
    public function invoice(): void
    {
        $this->authorize('ventas.editar');
        $this->ensureEditable();

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
