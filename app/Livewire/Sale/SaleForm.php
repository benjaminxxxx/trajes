<?php

namespace App\Livewire\Sale;

use App\Livewire\Shared\ManagesCartItems;
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

    public function mount(): void
    {
        $this->sale_date = now()->toDateString();
    }

    #[On('gallery-product-added')]
    public function handleGalleryProduct(string $context, int $productId, string $productName, ?string $code, ?int $categoryId, float $price): void
    {
        if ($context !== 'sale-gallery') {
            return;
        }

        $this->addCartItem([
            'product_id' => $productId,
            'product_name' => $productName,
            'product_code' => $code,
            'category_id' => $categoryId,
            'size_id' => null,
            'color_id' => null,
            'quantity' => 1,
            'unit_price' => $price,
            'discount_percent' => 0,
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

            $this->reset(['client_id', 'client_name', 'document_number', 'has_invoice_pending', 'notes', 'items']);
            $this->document_type = 'nota_venta';
            $this->sale_date = now()->toDateString();
            $this->clientPickerVersion++;

            Flux::toast(variant: 'success', text: "Venta {$sale->code} registrada correctamente.");
            $this->dispatch('saleSaved', saleId: $sale->id);
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