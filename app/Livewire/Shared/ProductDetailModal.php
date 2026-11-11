<?php

namespace App\Livewire\Shared;

use App\Models\PhysicalProduct;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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
    /**
     * @param  string  $mode  'sale' | 'rental' | 'catalog' (catálogo para el cliente: se puede compartir)
     */
    #[On('product-detail-requested')]
    public function open(int $productId, string $mode = 'sale', string $context = ''): void
    {
        $product = Product::with(['category:id,name', 'characteristics', 'tags'])->find($productId);

        if (! $product) {
            return;
        }

        $isCatalog = $mode === 'catalog';
        // En el catálogo el precio y el stock son los de venta, o los de alquiler si no se vende.
        $priceMode = $isCatalog ? ($product->allow_sale ? 'sale' : 'rental') : $mode;

        $variants = $this->variantsWithStock($product, $priceMode);
        $prices = $variants->pluck($priceMode === 'rental' ? 'rental_price' : 'sale_price')->filter();
        $price = $prices->isNotEmpty() ? (float) $prices->min() : ($priceMode === 'rental' ? null : (float) $product->price);

        $this->dispatch('product-reel-open', product: [
            'id' => $product->id,
            'name' => $product->name,
            'code' => $product->code,
            'category' => $product->category?->name,
            'description' => $product->description,
            'price' => $price,
            'priceLabel' => $priceMode === 'rental' ? 'por alquiler' : 'venta',
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
            'shareable' => $isCatalog,
            // Solo para el vendedor (no se muestra al cliente salvo que lo abra).
            'internal' => $isCatalog && Gate::allows('catalogo.costos') ? $this->internalInfo($product, $variants, $price) : null,
        ]);
    }

    /**
     * Costo, margen y últimas compras con su proveedor, para que el vendedor
     * sepa hasta dónde puede bajar el precio.
     *
     * @param  Collection<int, ProductVariant>  $variants
     * @return array{cost: float|null, margin: float|null, marginPercent: float|null, purchases: array<int, array{date: string|null, supplier: string, document: string|null, quantity: int, unitCost: float}>}
     */
    protected function internalInfo(Product $product, Collection $variants, ?float $price): array
    {
        // Costo promedio ponderado por el stock actual; si no hay stock, el último promedio o el costo del producto.
        $stocked = $variants->filter(fn (ProductVariant $variant) => (int) $variant->stock > 0 && (float) $variant->average_cost > 0);
        $cost = $stocked->isNotEmpty()
            ? round($stocked->sum(fn (ProductVariant $variant) => $variant->stock * (float) $variant->average_cost) / $stocked->sum('stock'), 2)
            : (((float) $variants->max('average_cost')) ?: ($product->purchase_price !== null ? (float) $product->purchase_price : null));

        $purchases = PurchaseDetail::query()
            ->with('purchase.supplier')
            ->whereIn('product_variant_id', $variants->pluck('id'))
            ->whereHas('purchase', fn ($query) => $query->where('status', Purchase::STATUS_COMPLETED))
            ->latest('id')
            ->limit(3)
            ->get()
            ->map(fn (PurchaseDetail $detail) => [
                'date' => $detail->purchase->issue_date?->format('d/m/Y'),
                'supplier' => $detail->purchase->supplier?->display_name ?? 'Sin proveedor',
                'document' => $detail->purchase->document_number,
                'quantity' => (int) $detail->quantity,
                'unitCost' => round((float) $detail->subtotal / max(1, (int) $detail->quantity), 2),
            ])
            ->all();

        $margin = $cost !== null && $price !== null ? round($price - $cost, 2) : null;

        return [
            'cost' => $cost,
            'margin' => $margin,
            'marginPercent' => $margin !== null && $price > 0 ? round($margin / $price * 100, 1) : null,
            'purchases' => $purchases,
        ];
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
