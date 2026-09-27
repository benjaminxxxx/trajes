<?php

namespace App\Livewire\Shared;

use App\Models\PhysicalProduct;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Grilla de productos reutilizada por Venta y Alquiler. No sabe nada de
 * carritos: solo busca/filtra y dispara `gallery-product-added` con lo que
 * el formulario padre necesita para agregar su propia línea (igual patrón
 * que el buscador de producto en Compras, pero en formato grilla con stock
 * visible, como en el mock de catálogo).
 */
class ProductGallery extends Component
{
    use WithPagination;

    public string $mode = 'sale'; // 'sale' | 'rental' — decide qué precio/disponibilidad mostrar
    public string $fieldContext = 'gallery';
    public string $search = '';
    public bool $onlyAvailable = false;

    public function mount(string $mode = 'sale', string $fieldContext = 'gallery'): void
    {
        $this->mode = $mode;
        $this->fieldContext = $fieldContext;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingOnlyAvailable(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function products(): LengthAwarePaginator
    {
        $query = Product::query()
            ->with('category')
            ->where('is_active', true)
            ->when($this->mode === 'sale', fn ($q) => $q->where('allow_sale', true))
            ->when($this->mode === 'rental', fn ($q) => $q->where('allow_rental', true))
            ->when($this->search !== '', function ($q) {
                $q->where(function ($q2) {
                    $q2->where('name', 'like', "%{$this->search}%")
                        ->orWhere('code', 'like', "%{$this->search}%");
                });
            })
            ->withSum('variants as total_stock', 'stock')
            ->withMin('variants as sale_price_from', 'sale_price')
            ->withMin('variants as rental_price_from', 'rental_price');

        if ($this->mode === 'rental') {
            $query->addSelect([
                'available_units' => PhysicalProduct::selectRaw('count(*)')
                    ->join('product_variants', 'product_variants.id', '=', 'physical_products.product_variant_id')
                    ->whereColumn('product_variants.product_id', 'products.id')
                    ->where('physical_products.status', 'available'),
            ]);
        }

        if ($this->onlyAvailable) {
            $query->when(
                $this->mode === 'sale',
                fn ($q) => $q->having('total_stock', '>', 0),
                fn ($q) => $q->having('available_units', '>', 0),
            );
        }

        $paginated = $query->orderBy('name')->paginate(12);

        $this->attachSwatchesAndCover($paginated);

        return $paginated;
    }

    /**
     * Adjunta, por producto, la imagen de portada (ProductMedia primaria) y
     * los colores en los que existe (para los puntitos de la card). Se hace
     * en 2 consultas agrupadas en vez de N+1 por producto.
     */
    protected function attachSwatchesAndCover(LengthAwarePaginator $products): void
    {
        $ids = $products->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        $covers = ProductMedia::whereIn('product_id', $ids)
            ->where('is_primary', true)
            ->get()
            ->keyBy('product_id');

        $swatches = ProductVariant::whereIn('product_id', $ids)
            ->whereNotNull('color_id')
            ->with('color')
            ->get()
            ->groupBy('product_id')
            ->map(fn ($variants) => $variants->pluck('color')->unique('id')->values());

        foreach ($products as $product) {
            $product->cover_url = $covers[$product->id]->url ?? null;
            $product->swatch_colors = $swatches[$product->id] ?? collect();
        }
    }

    /**
     * Abre el modal de detalle (solo informativo: stock por variante,
     * fotos/videos). No agrega nada al carrito.
     */
    public function openDetail(int $productId): void
    {
        $this->dispatch('product-detail-requested', productId: $productId, mode: $this->mode);
    }

    public function addToCart(int $productId): void
    {
        $product = $this->products->firstWhere('id', $productId)
            ?? Product::withSum('variants as total_stock', 'stock')
                ->withMin('variants as sale_price_from', 'sale_price')
                ->withMin('variants as rental_price_from', 'rental_price')
                ->findOrFail($productId);

        $price = $this->mode === 'rental'
            ? ($product->rental_price_from ?? 0)
            : ($product->sale_price_from ?? $product->price);

        $this->dispatch(
            'gallery-product-added',
            context: $this->fieldContext,
            productId: $product->id,
            productName: $product->name,
            code: $product->code,
            categoryId: $product->category_id,
            price: (float) $price,
        );
    }

    public function render()
    {
        return view('livewire.shared.product-gallery');
    }
}