<?php

namespace App\Livewire\Sale;

use App\Livewire\Shared\ManagesCartItems;
use App\Models\Client;
use App\Models\PhysicalProduct;
use App\Models\Sale;
use App\Services\Sale\SaleService;
use Flux\Flux;
use Livewire\Attributes\On;
use Livewire\Component;

class SaleForm extends Component
{
    use ManagesCartItems;

    public ?int $client_id = null;

    public ?string $client_name = null;

    public string $document_type = 'nota_venta';

    public ?string $document_number = null;

    public bool $has_invoice_pending = false;

    public ?string $sale_date = null;

    public ?string $notes = null;

    public array $items = [];

    // Fuerza a que el buscador de cliente se remonte limpio tras guardar.
    public int $clientPickerVersion = 0;

    // Código de la venta anulada desde la que se precargó el formulario.
    public ?string $redoFromCode = null;

    public function mount(): void
    {
        $this->sale_date = now()->toDateString();

        if ($fromId = request()->integer('from')) {
            $this->prefillFromCancelledSale($fromId);
        }

        if ($unitId = request()->integer('prenda')) {
            $this->prefillPhysicalUnit($unitId);
        }
    }

    /**
     * Precarga una prenda física puntual (ej. el cliente que alquiló un terno
     * decide comprarlo al devolverlo). Solo si está disponible para venta.
     */
    protected function prefillPhysicalUnit(int $unitId): void
    {
        $unit = PhysicalProduct::with(['variant.product', 'variant.size:id,name', 'variant.color:id,name'])
            ->where('status', 'available')
            ->find($unitId);
        $product = $unit?->variant?->product;

        if (! $product?->allow_sale) {
            return;
        }

        $price = (float) ($unit->suggested_sale_price ?? $unit->variant->sale_price ?? $product->price ?? 0);

        $this->addPickedItems([[
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_code' => $product->code,
            'category_id' => $product->category_id,
            'size_id' => $unit->variant->size_id,
            'color_id' => $unit->variant->color_id,
            'variant_label' => $unit->variant->label(),
            'physical_product_id' => $unit->id,
            'physical_code' => $unit->code,
            'quantity' => 1,
            'unit_price' => $price,
        ]], fn (array $item) => [...$item, 'line_total' => round($price, 2)]);
    }

    /**
     * Precarga cabecera y líneas de una venta anulada para registrarla de
     * nuevo con los datos corregidos (la anulada se conserva tal cual).
     */
    protected function prefillFromCancelledSale(int $saleId): void
    {
        $sale = Sale::with([
            'client:id,display_name',
            'details.variant.product:id,name,code,category_id',
            'details.variant.size:id,name',
            'details.variant.color:id,name',
            'details.physicalProduct:id,code,status',
        ])
            ->where('status', Sale::STATUS_CANCELLED)
            ->find($saleId);

        if (! $sale) {
            return;
        }

        $this->redoFromCode = $sale->code;
        $this->client_id = $sale->client_id;
        $this->client_name = $sale->client?->display_name;
        $this->document_type = $sale->document_type;
        $this->has_invoice_pending = $sale->has_invoice_pending;
        $this->notes = $sale->notes;

        foreach ($sale->details as $detail) {
            $product = $detail->variant?->product;

            if (! $product) {
                continue;
            }

            // En BD se guarda sin IGV; el formulario trabaja con IGV incluido.
            $lineTotal = round((float) $detail->subtotal * (1 + SaleService::TAX_RATE), 2);
            $unit = $detail->physicalProduct?->status === 'available' ? $detail->physicalProduct : null;

            $this->addCartItem([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_code' => $product->code,
                'category_id' => $product->category_id,
                'size_id' => $detail->variant->size_id,
                'color_id' => $detail->variant->color_id,
                'physical_product_id' => $unit?->id,
                'physical_code' => $unit?->code,
                'variant_label' => $detail->variant->label(),
                'quantity' => $detail->quantity,
                'unit_price' => round($lineTotal / $detail->quantity, 2),
                'line_total' => $lineTotal,
                'discount_percent' => 0,
            ]);
        }
    }

    #[On('product-picker-selected')]
    public function handlePickedItems(string $context, array $items): void
    {
        if ($context !== 'sale-gallery') {
            return;
        }

        // El subtotal de la línea (con IGV) sigue a cantidad × precio al agregar o sumar unidades.
        $this->addPickedItems($items, fn (array $item) => [
            ...$item,
            'line_total' => round((int) $item['quantity'] * (float) $item['unit_price'], 2),
        ]);
    }

    #[On('entity-selected')]
    public function handleEntitySelected(string $context, int $id, string $label): void
    {
        if ($context === 'sale-client') {
            $this->client_id = $id;
            $this->client_name = $label;
        }
    }

    /**
     * Cliente recién creado desde el botón "+": se deja seleccionado y se
     * remonta el buscador para que lo muestre.
     */
    #[On('client-created')]
    public function selectCreatedClient(int $clientId): void
    {
        $client = Client::find($clientId);

        if ($client) {
            $this->client_id = $client->id;
            $this->client_name = $client->display_name;
            $this->clientPickerVersion++;
        }
    }

    #[On('entity-cleared')]
    public function handleEntityCleared(string $context): void
    {
        if ($context === 'sale-client') {
            $this->client_id = null;
            $this->client_name = null;
        }
    }

    public function save(SaleService $service): void
    {
        $this->authorize('ventas.registrar');

        $this->validate([
            'document_type' => 'required|in:nota_venta,boleta,factura',
            'sale_date' => 'required|date',
            'items' => 'required|array|min:1',
        ], [
            'items.required' => 'Agrega al menos un producto a la venta.',
        ]);

        try {
            $sale = $service->register([
                'client_id' => $this->client_id,
                'document_type' => $this->document_type,
                'document_number' => $this->document_number,
                'has_invoice_pending' => $this->has_invoice_pending,
                'sale_date' => $this->sale_date,
                'notes' => $this->notes,
            ], $this->normalizedLines());

            $this->reset(['client_id', 'client_name', 'document_number', 'has_invoice_pending', 'notes', 'items', 'redoFromCode']);
            $this->document_type = 'nota_venta';
            $this->sale_date = now()->toDateString();
            $this->clientPickerVersion++;

            Flux::toast(variant: 'success', text: "Venta {$sale->code} registrada correctamente.");
            $this->dispatch('saleSaved', saleId: $sale->id);

            // Tras registrar se muestra el comprobante listo para compartir.
            $this->redirectRoute('receipts.show', ['type' => 'venta', 'id' => $sale->id], navigate: true);
        } catch (\Throwable $th) {
            report($th);
            Flux::toast(variant: 'danger', text: 'No se pudo registrar la venta: '.$th->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.sale.sale-form');
    }
}
