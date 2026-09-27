<?php

namespace App\Livewire\Shared;

use App\Models\PhysicalProduct;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Puramente informativo: no agrega nada al carrito. Se abre desde
 * ProductGallery (evento `product-detail-requested`) para responder "¿cuánto
 * tengo de CADA talla/color?" y mostrar fotos/videos del producto.
 */
class ProductDetailModal extends Component
{
    public bool $show = false;
    public ?int $productId = null;
    public string $mode = 'sale';

    #[On('product-detail-requested')]
    public function open(int $productId, string $mode = 'sale'): void
    {
        $this->productId = $productId;
        $this->mode = $mode;
        $this->show = true;
    }

    #[Computed]
    public function product(): ?Product
    {
        if (! $this->productId) {
            return null;
        }

        return Product::with('category')->find($this->productId);
    }

    #[Computed]
    public function variants()
    {
        if (! $this->productId) {
            return collect();
        }

        $variants = ProductVariant::where('product_id', $this->productId)
            ->with(['color', 'size'])
            ->get();

        if ($this->mode === 'rental') {
            $availableByVariant = PhysicalProduct::whereIn('product_variant_id', $variants->pluck('id'))
                ->where('status', 'available')
                ->select('product_variant_id', DB::raw('count(*) as cnt'))
                ->groupBy('product_variant_id')
                ->pluck('cnt', 'product_variant_id');

            $variants->each(fn ($v) => $v->available_units = $availableByVariant[$v->id] ?? 0);
        }

        return $variants
            ->sortBy([
                fn ($v) => $v->size?->sort_order ?? 0,
                fn ($v) => $v->color?->name ?? '',
            ])
            ->values();
    }

    /**
     * Colores en los que existe el producto (para los swatches del slider).
     */
    #[Computed]
    public function colors()
    {
        return $this->variants->pluck('color')->filter()->unique('id')->values();
    }

    /**
     * Payload liviano para Alpine (evita mandarle modelos Eloquent completos
     * al navegador). Se filtra por color en el propio Alpine, sin ir al
     * servidor.
     */
    #[Computed]
    public function mediaPayload(): array
    {
        if (! $this->productId) {
            return [];
        }

        return ProductMedia::where('product_id', $this->productId)
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (ProductMedia $media) => [
                'url' => $media->url,
                'file_type' => $media->file_type,
                'color_id' => $media->color_id,
            ])
            ->all();
    }

    public function close(): void
    {
        $this->show = false;
        $this->productId = null;
    }

    public function render()
    {
        return view('livewire.shared.product-detail-modal');
    }
}