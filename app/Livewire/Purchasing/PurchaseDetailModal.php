<?php

namespace App\Livewire\Purchasing;

use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\Sale;
use App\Services\Purchasing\PurchaseService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Detalle y edición de una compra. La cabecera (proveedor, comprobante,
 * fecha, notas) se edita libremente porque no toca stock; en el detalle solo
 * se permite REDUCIR cantidades, validado por PurchaseService.
 */
class PurchaseDetailModal extends Component
{
    public ?int $purchaseId = null;

    public bool $isOpen = false;

    public ?int $supplier_id = null;

    public ?string $supplier_name = null;

    public string $document_type = 'nota_venta';

    public ?string $document_number = null;

    public bool $has_invoice_pending = false;

    public ?string $issue_date = null;

    public ?string $notes = null;

    /** @var array<int, int> [purchase_detail_id => nueva cantidad] */
    public array $quantities = [];

    #[On('open-purchase-detail')]
    public function loadPurchase(int $id): void
    {
        $this->resetValidation();
        $this->reset();

        $this->purchaseId = $id;
        unset($this->purchase);

        $purchase = $this->purchase;

        $this->supplier_id = $purchase->supplier_id;
        $this->supplier_name = $purchase->supplier?->display_name;
        $this->document_type = $purchase->document_type;
        $this->document_number = $purchase->document_number;
        $this->has_invoice_pending = $purchase->has_invoice_pending;
        $this->issue_date = $purchase->issue_date?->format('Y-m-d');
        $this->notes = $purchase->notes;
        $this->quantities = $purchase->details->pluck('quantity', 'id')->all();

        $this->isOpen = true;
    }

    #[Computed]
    public function purchase(): ?Purchase
    {
        if (! $this->purchaseId) {
            return null;
        }

        return Purchase::with([
            'supplier:id,display_name,document_number',
            'user:id,name',
            'details.variant.product:id,name,code,allow_rental',
            'details.variant.size:id,name',
            'details.variant.color:id,name',
        ])
            ->withCount('details')
            ->findOrFail($this->purchaseId);
    }

    /**
     * Cuántas unidades se pueden quitar de cada línea sin romper el stock.
     *
     * @return array<int, int>
     */
    #[Computed]
    public function removable(): array
    {
        $service = app(PurchaseService::class);

        return $this->purchase?->details
            ->mapWithKeys(fn (PurchaseDetail $detail) => [$detail->id => $service->removableQuantity($detail)])
            ->all() ?? [];
    }

    #[On('entity-selected')]
    public function handleEntitySelected(string $context, int $id, string $label): void
    {
        if ($context === 'purchase-edit-supplier') {
            $this->supplier_id = $id;
            $this->supplier_name = $label;
        }
    }

    #[On('entity-cleared')]
    public function handleEntityCleared(string $context): void
    {
        if ($context === 'purchase-edit-supplier') {
            $this->supplier_id = null;
            $this->supplier_name = null;
        }
    }

    public function save(PurchaseService $service): void
    {
        $this->authorize('compras.editar');

        $validated = $this->validate([
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'document_type' => ['required', Rule::in(array_keys(Sale::DOCUMENT_TYPES))],
            'document_number' => ['nullable', 'string', 'max:255'],
            'has_invoice_pending' => ['boolean'],
            'issue_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // Si cambia entre factura y otro comprobante, el servicio recalcula el costo de las prendas.
        $service->updateHeader($this->purchase, $validated);

        $this->isOpen = false;
        $this->dispatch('purchaseUpdated');
        Flux::toast(variant: 'success', text: 'Compra actualizada correctamente.');
    }

    public function reduceQuantity(int $detailId, PurchaseService $service): void
    {
        $this->authorize('compras.editar');

        $detail = $this->purchase->details->firstWhere('id', $detailId);
        abort_unless($detail, 404);

        $this->validate([
            "quantities.{$detailId}" => ['required', 'integer', 'min:1', 'max:'.($detail->quantity - 1)],
        ], [
            "quantities.{$detailId}.max" => 'Solo se puede reducir la cantidad comprada.',
        ], ["quantities.{$detailId}" => 'cantidad']);

        try {
            $service->reduceLineQuantity($detail, (int) $this->quantities[$detailId]);
        } catch (\RuntimeException $e) {
            $this->addError("quantities.{$detailId}", $e->getMessage());

            return;
        }

        unset($this->purchase, $this->removable);
        $this->quantities = $this->purchase->details->pluck('quantity', 'id')->all();

        $this->dispatch('purchaseUpdated');
        Flux::toast(variant: 'success', text: 'Cantidad corregida. El stock fue actualizado.');
    }

    public function render(): View
    {
        return view('livewire.purchasing.purchase-detail-modal');
    }
}
