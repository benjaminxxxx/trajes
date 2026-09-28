<?php

namespace App\Livewire\Purchasing;

use App\Models\Category;
use App\Models\Supplier;
use App\Services\Purchasing\PurchaseService;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class PurchaseForm extends Component
{
    // Cabecera
    public ?int $supplier_id = null;

    public ?string $supplier_name = null;

    public string $document_type = 'nota_venta';

    public ?string $document_number = null;

    public bool $has_invoice_pending = false;

    public ?string $issue_date = null;

    public ?string $notes = null;

    // Detalle: cada item es un array asociativo (no un modelo, es solo borrador)
    public array $items = [];

    // Cambia tras cada guardado exitoso para forzar que el
    // entity-search-select del proveedor se remonte limpio.
    public int $supplierPickerVersion = 0;

    // Tallas/colores por categoría, resueltos una sola vez por request
    // (no se persiste entre requests, así que siempre sale fresco).
    protected array $categoryOptionsCache = [];

    public function mount(): void
    {
        $this->issue_date = now()->toDateString();
    }

    #[On('entity-selected')]
    public function handleEntitySelected(string $context, int $id, string $label, ?string $subtitle = null, array $meta = []): void
    {
        match ($context) {
            'purchase-supplier' => $this->setSupplier($id, $label),
            'purchase-product' => $this->addProductToItems($id, $label, $subtitle, $meta),
            default => null,
        };
    }

    /**
     * Proveedor recién creado desde el botón "+": se deja seleccionado y se
     * remonta el buscador para que lo muestre.
     */
    #[On('supplier-created')]
    public function selectCreatedSupplier(int $supplierId): void
    {
        $supplier = Supplier::find($supplierId);

        if ($supplier) {
            $this->setSupplier($supplier->id, $supplier->display_name);
            $this->supplierPickerVersion++;
        }
    }

    #[On('entity-cleared')]
    public function handleEntityCleared(string $context): void
    {
        if ($context === 'purchase-supplier') {
            $this->supplier_id = null;
            $this->supplier_name = null;
        }
    }

    protected function setSupplier(int $id, string $label): void
    {
        $this->supplier_id = $id;
        $this->supplier_name = $label;
    }

    /**
     * El buscador de producto está en modo `resetAfterSelect`. Cada clic
     * agrega una fila NUEVA e independiente: a esta altura no sabemos
     * todavía la talla/color, así que fusionar cantidades por producto sería
     * adivinar y se rompe apenas la 2da unidad es otra talla. El usuario
     * define talla/color/cantidad por fila, y puede duplicar una fila ya
     * configurada (ver duplicateItem) para agregar variantes rápido.
     */
    protected function addProductToItems(int $productId, string $productName, ?string $code, array $meta): void
    {
        $this->items[(string) Str::uuid()] = [
            'product_id' => $productId,
            'product_name' => $productName,
            'product_code' => $code,
            'category_id' => $meta['category_id'] ?? null,
            'size_id' => null,
            'color_id' => null,
            'quantity' => 1,
            'unit_price' => (float) ($meta['price'] ?? 0),
            'discount_percent' => 0,
        ];
    }

    public function removeItem(string $key): void
    {
        unset($this->items[$key]);
    }

    /**
     * Clona una fila ya configurada (mismo producto/precio) para que agregar
     * "la misma camisa en otra talla" sea un clic + cambiar talla, en vez de
     * volver a buscar el producto.
     */
    public function duplicateItem(string $key): void
    {
        if (! isset($this->items[$key])) {
            return;
        }

        $this->items[(string) Str::uuid()] = $this->items[$key];
    }

    /**
     * Aviso NO bloqueante: hay otra fila con el mismo producto+talla+color.
     * Puede ser un descuido (fusionar manualmente) o intencional (ej. la
     * 4ta unidad vino con un descuento distinto) — no lo impedimos, solo lo
     * señalamos para que el usuario decida.
     */
    public function isDuplicateVariant(string $key): bool
    {
        $item = $this->items[$key] ?? null;

        if (! $item || ! $item['size_id'] || ! $item['color_id']) {
            return false;
        }

        foreach ($this->items as $otherKey => $other) {
            if ($otherKey === $key) {
                continue;
            }

            if ((int) $other['product_id'] === (int) $item['product_id']
                && (int) $other['size_id'] === (int) $item['size_id']
                && (int) $other['color_id'] === (int) $item['color_id']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tallas/colores habilitados para la categoría del producto (ver
     * category_size / category_color), para no cargar el catálogo completo
     * en cada fila del detalle.
     */
    public function sizeOptions(?int $categoryId): Collection
    {
        return $this->categoryOptions($categoryId)['sizes'];
    }

    public function colorOptions(?int $categoryId): Collection
    {
        return $this->categoryOptions($categoryId)['colors'];
    }

    protected function categoryOptions(?int $categoryId): array
    {
        if (! $categoryId) {
            return ['sizes' => collect(), 'colors' => collect()];
        }

        if (! isset($this->categoryOptionsCache[$categoryId])) {
            $category = Category::with('sizes', 'colors')->find($categoryId);

            $this->categoryOptionsCache[$categoryId] = [
                'sizes' => $category?->sizes ?? collect(),
                'colors' => $category?->colors ?? collect(),
            ];
        }

        return $this->categoryOptionsCache[$categoryId];
    }

    #[Computed]
    public function subtotal(): float
    {
        return round(collect($this->items)->sum(
            fn ($item) => $item['quantity'] * $item['unit_price'] * (1 - $item['discount_percent'] / 100)
        ), 2);
    }

    #[Computed]
    public function tax(): float
    {
        return round($this->subtotal * 0.18, 2);
    }

    #[Computed]
    public function total(): float
    {
        return round($this->subtotal + $this->tax, 2);
    }

    public function save(PurchaseService $service): void
    {
        $this->authorize('compras.registrar');

        $this->validate([
            'document_type' => 'required|in:nota_venta,boleta,factura',
            'issue_date' => 'required|date',
            'items' => 'required|array|min:1',
        ], [
            'items.required' => 'Agrega al menos un producto a la compra.',
        ]);

        try {
            $lines = array_map(function (array $item) {
                $item['size_id'] = $item['size_id'] !== '' && $item['size_id'] !== null ? (int) $item['size_id'] : null;
                $item['color_id'] = $item['color_id'] !== '' && $item['color_id'] !== null ? (int) $item['color_id'] : null;

                return $item;
            }, array_values($this->items));

            $purchase = $service->register([
                'supplier_id' => $this->supplier_id,
                'document_type' => $this->document_type,
                'document_number' => $this->document_number,
                'has_invoice_pending' => $this->has_invoice_pending,
                'issue_date' => $this->issue_date,
                'notes' => $this->notes,
            ], $lines);

            $this->reset(['supplier_id', 'supplier_name', 'document_number', 'has_invoice_pending', 'notes', 'items']);
            $this->document_type = 'nota_venta';
            $this->issue_date = now()->toDateString();
            $this->supplierPickerVersion++;

            Flux::toast(variant: 'success', text: "Compra #{$purchase->id} registrada correctamente.");
            $this->dispatch('purchaseSaved', purchaseId: $purchase->id);
        } catch (\Throwable $th) {
            report($th);
            Flux::toast(variant: 'danger', text: 'No se pudo registrar la compra: '.$th->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.purchasing.purchase-form');
    }
}
