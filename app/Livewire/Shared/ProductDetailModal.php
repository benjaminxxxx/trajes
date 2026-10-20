<?php

namespace App\Livewire\Shared;

use App\Models\PhysicalProduct;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Visor tipo reel del producto, a pantalla completa: fotos y videos uno
 * debajo de otro (se desliza hacia arriba), sin recortarlos sean verticales
 * u horizontales, con el precio y el stock por talla/color flotando encima.
 *
 * Se abre desde ProductGallery (`product-detail-requested`). Este componente
 * solo arma los datos y los manda al navegador (`product-reel-open`); el
 * visor lo maneja Alpine, así un re-render de Livewire no reinicia el video.
 */
class ProductDetailModal extends Component
{
    #[On('product-detail-requested')]
    public function open(int $productId, string $mode = 'sale', string $context = ''): void
    {
        $product = Product::with(['category:id,name', 'characteristics', 'tags'])->find($productId);

        if (! $product) {
            return;
        }

        $variants = $this->variantsWithStock($product, $mode);
        $prices = $variants->pluck($mode === 'rental' ? 'rental_price' : 'sale_price')->filter();
        $price = $prices->isNotEmpty() ? (float) $prices->min() : ($mode === 'rental' ? null : (float) $product->price);

        $this->dispatch('product-reel-open', product: [
            'id' => $product->id,
            'name' => $product->name,
            'code' => $product->code,
            'category' => $product->category?->name,
            'description' => $product->description,
            'price' => $price,
            'priceLabel' => $mode === 'rental' ? 'por alquiler' : 'venta',
            'highlights' => [...$product->characteristics->pluck('value'), ...$product->tags->pluck('name')],
            // Solo lo que hay: si no hay stock registrado, el visor no muestra nada de stock.
            'variants' => $variants
                ->filter(fn (ProductVariant $variant) => $variant->available > 0)
                ->map(fn (ProductVariant $variant) => [
                    'label' => collect([$variant->size?->name, $variant->color?->name])->filter()->implode(' · ') ?: 'Única',
                    'hex' => $variant->color?->hex_code,
                    'qty' => $variant->available,
                ])->values()->all(),
            'available' => $variants->sum('available'),
            'media' => ProductMedia::where('product_id', $product->id)
                ->orderByDesc('is_primary')
                ->orderBy('sort_order')
                ->get()
                ->map(fn (ProductMedia $media) => ['url' => $media->url, 'type' => $media->file_type])
                ->all(),
            'mode' => $mode,
            'context' => $context,
        ]);
    }

    /**
     * Variantes ordenadas por talla, con lo disponible: stock en venta,
     * prendas libres en alquiler.
     *
     * @return Collection<int, ProductVariant>
     */
    protected function variantsWithStock(Product $product, string $mode): Collection
    {
        $variants = ProductVariant::where('product_id', $product->id)->with(['color', 'size'])->get();

        $availableByVariant = $mode === 'rental'
            ? PhysicalProduct::whereIn('product_variant_id', $variants->pluck('id'))
                ->where('status', 'available')
                ->select('product_variant_id', DB::raw('count(*) as cnt'))
                ->groupBy('product_variant_id')
                ->pluck('cnt', 'product_variant_id')
            : $variants->pluck('stock', 'id');

        return $variants
            ->each(fn (ProductVariant $variant) => $variant->available = (int) ($availableByVariant[$variant->id] ?? 0))
            ->sortBy([
                fn ($variant) => $variant->size?->sort_order ?? 0,
                fn ($variant) => $variant->color?->name ?? '',
            ])
            ->values();
    }

    public function render(): View
    {
        return view('livewire.shared.product-detail-modal');
    }
}
