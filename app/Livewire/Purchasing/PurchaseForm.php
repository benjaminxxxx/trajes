<?php

namespace App\Livewire\Purchasing;

use App\Models\Category;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\Purchasing\PurchaseService;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
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

    // Los precios se escriben como figuran en el comprobante: casi siempre
    // ya traen el IGV incluido.
    public bool $prices_include_tax = true;

    // Total impreso en el comprobante, para comprobar que lo registrado cuadra.
    public float|string|null $document_total = null;

    // Borrador en edición (se mantiene en la URL para poder retomarlo).
    #[Url(as: 'borrador')]
    public ?int $draftId = null;

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

        if ($this->draftId) {
            $this->loadDraft($this->draftId);
        }

        // "Comprar más" desde el catálogo: el producto ya entra a la compra.
        if ($productId = request()->integer('producto')) {
            $this->addCreatedProduct($productId, 'purchase');
        }
    }

    protected function loadDraft(int $draftId): void
    {
        $draft = Purchase::with('supplier:id,display_name')->find($draftId);

        if (! $draft?->isDraft()) {
            $this->draftId = null;

            return;
        }

        $this->supplier_id = $draft->supplier_id;
        $this->supplier_name = $draft->supplier?->display_name;
        $this->document_type = $draft->document_type;
        $this->document_number = $draft->document_number;
        $this->has_invoice_pending = $draft->has_invoice_pending;
        $this->prices_include_tax = $draft->prices_include_tax;
        $this->document_total = $draft->document_total === null ? null : (float) $draft->document_total;
        $this->issue_date = $draft->issue_date?->toDateString();
        $this->notes = $draft->notes;
        $this->items = collect($draft->draft_items ?? [])
            ->mapWithKeys(fn (array $item) => [(string) Str::uuid() => $item])
            ->all();
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

    /**
     * Producto recién creado desde el botón "+" del buscador: entra directo
     * al detalle, sin tener que buscarlo.
     */
    #[On('product-saved')]
    public function addCreatedProduct(int $productId, string $context = ''): void
    {
        if ($context !== 'purchase') {
            return;
        }

        $product = Product::find($productId);

        if ($product) {
            $this->addProductToItems($product->id, $product->name, $product->code, [
                'purchase_price' => (float) $product->purchase_price,
                'category_id' => $product->category_id,
            ]);
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
            'unit_price' => (float) ($meta['purchase_price'] ?? 0),
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
                // Las tallas/colores desactivados ya no se ofrecen para nuevas compras.
                'sizes' => $category?->sizes->where('is_active', true)->values() ?? collect(),
                'colors' => $category?->colors->where('is_active', true)->values() ?? collect(),
            ];
        }

        return $this->categoryOptionsCache[$categoryId];
    }

    /**
     * @return array{subtotal: float, tax: float, total: float}
     */
    #[Computed]
    public function totals(): array
    {
        $service = app(PurchaseService::class);

        return $service->totals($service->linesAmount(array_values($this->items)), $this->prices_include_tax);
    }

    /**
     * Total registrado menos el total del comprobante (0 = cuadra).
     */
    #[Computed]
    public function documentDifference(): ?float
    {
        if (! is_numeric($this->document_total)) {
            return null;
        }

        return round($this->totals['total'] - (float) $this->document_total, 2);
    }

    #[Computed]
    public function itemsWithoutVariant(): int
    {
        return collect($this->items)->filter(fn (array $item) => blank($item['size_id']) || blank($item['color_id']))->count();
    }

    /**
     * Guarda lo avanzado sin tocar stock ni caja, para confirmarlo cuando se
     * hayan revisado tallas, colores y el total contra el comprobante.
     */
    public function saveDraft(PurchaseService $service): void
    {
        $this->authorize('compras.registrar');
        $this->validate($this->validationRules(requireItems: false));

        try {
            $draft = $service->saveDraft($this->header(), $this->lines(), $this->currentDraft());
        } catch (\RuntimeException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->draftId = $draft->id;

        Flux::toast(variant: 'success', text: "Borrador #{$draft->id} guardado. Aún no afecta stock ni caja.");
    }

    public function save(PurchaseService $service): void
    {
        $this->authorize('compras.registrar');
        $this->validate($this->validationRules(requireItems: true), [
            'items.required' => 'Agrega al menos un producto a la compra.',
        ]);

        try {
            $purchase = $service->register($this->header(), $this->lines(), $this->currentDraft());

            $this->resetForm();

            Flux::toast(variant: 'success', text: "Compra #{$purchase->id} registrada: S/ ".number_format((float) $purchase->total, 2).' salieron de caja.');
            $this->dispatch('purchaseSaved', purchaseId: $purchase->id);
        } catch (\Throwable $th) {
            report($th);
            Flux::toast(variant: 'danger', text: 'No se pudo registrar la compra: '.$th->getMessage());
        }
    }

    public function deleteDraft(PurchaseService $service): void
    {
        $this->authorize('compras.registrar');

        if ($draft = $this->currentDraft()) {
            try {
                $service->deleteDraft($draft);
            } catch (\RuntimeException $e) {
                Flux::toast(variant: 'danger', text: $e->getMessage());

                return;
            }
        }

        $this->resetForm();
        Flux::toast(text: 'Borrador eliminado.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validationRules(bool $requireItems): array
    {
        return [
            'document_type' => 'required|in:nota_venta,boleta,factura',
            'issue_date' => 'required|date',
            'document_total' => 'nullable|numeric|min:0',
            'items' => $requireItems ? 'required|array|min:1' : 'array',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function header(): array
    {
        return [
            'supplier_id' => $this->supplier_id,
            'document_type' => $this->document_type,
            'document_number' => $this->document_number,
            'has_invoice_pending' => $this->has_invoice_pending,
            'prices_include_tax' => $this->prices_include_tax,
            'document_total' => is_numeric($this->document_total) ? round((float) $this->document_total, 2) : null,
            'issue_date' => $this->issue_date,
            'notes' => $this->notes,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function lines(): array
    {
        return array_map(function (array $item) {
            $item['size_id'] = filled($item['size_id']) ? (int) $item['size_id'] : null;
            $item['color_id'] = filled($item['color_id']) ? (int) $item['color_id'] : null;
            $item['discount_percent'] = (float) ($item['discount_percent'] ?: 0);

            return $item;
        }, array_values($this->items));
    }

    protected function currentDraft(): ?Purchase
    {
        return $this->draftId ? Purchase::find($this->draftId) : null;
    }

    protected function resetForm(): void
    {
        $this->reset(['supplier_id', 'supplier_name', 'document_number', 'has_invoice_pending', 'document_total', 'notes', 'items', 'draftId']);
        $this->document_type = 'nota_venta';
        $this->prices_include_tax = true;
        $this->issue_date = now()->toDateString();
        $this->supplierPickerVersion++;
    }

    public function render()
    {
        return view('livewire.purchasing.purchase-form');
    }
}
