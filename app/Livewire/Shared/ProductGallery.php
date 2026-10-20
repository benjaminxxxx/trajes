<?php

namespace App\Livewire\Shared;

use App\Models\PhysicalProduct;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Services\Inventory\ProductSearch;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Grilla de productos reutilizada por Venta y Alquiler. No sabe nada de
 * carritos: solo busca/filtra y, al agregar, abre el ProductPicker
 * (`product-picker-requested`), que es quien arma las líneas del carrito
 * con la talla/color y la disponibilidad real.
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
            ->when(trim($this->search) !== '', fn ($q) => app(ProductSearch::class)->apply($q, $this->search))
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
     * Cómo se entendió cada palabra buscada ("rojo" → Color, "l" → Talla).
     *
     * @return array<int, array{word: string, kind: string}>
     */
    #[Computed]
    public function searchChips(): array
    {
        return trim($this->search) === '' ? [] : app(ProductSearch::class)->interpret($this->search);
    }

    public function removeSearchWord(string $word): void
    {
        $this->search = collect(app(ProductSearch::class)->words($this->search))
            ->reject(fn (string $existing) => $existing === mb_strtolower($word))
            ->implode(' ');
        $this->resetPage();
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

        // Portada: la principal o, si no hay, el primer medio (puede ser un video).
        $covers = ProductMedia::whereIn('product_id', $ids)
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->get()
            ->unique('product_id')
            ->keyBy('product_id');

        $swatches = ProductVariant::whereIn('product_id', $ids)
            ->whereNotNull('color_id')
            ->with('color')
            ->get()
            ->groupBy('product_id')
            ->map(fn ($variants) => $variants->pluck('color')->unique('id')->values());

        foreach ($products as $product) {
            $product->cover_url = $covers[$product->id]->url ?? null;
            $product->cover_is_video = ($covers[$product->id]->file_type ?? null) === 'video';
            $product->swatch_colors = $swatches[$product->id] ?? collect();
        }
    }

    /**
     * Abre el modal de detalle (solo informativo: stock por variante,
     * fotos/videos). No agrega nada al carrito.
     */
    public function openDetail(int $productId): void
    {
        $this->dispatch('product-detail-requested', productId: $productId, mode: $this->mode, context: $this->fieldContext);
    }

    /**
     * Abre el ProductPicker: ahí se elige talla/color con disponibilidad real
     * y, según el producto, las prendas por código o la cantidad.
     */
    public function addToCart(int $productId): void
    {
        $this->dispatch('product-picker-requested', context: $this->fieldContext, productId: $productId, mode: $this->mode);
    }

    public function render()
    {
        return view('livewire.shared.product-gallery');
    }
}
