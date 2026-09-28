<?php

namespace App\Livewire\Shared;

use App\Models\PhysicalProduct;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Selector de producto en pasos: (1) perfil del producto con lo disponible
 * por talla/color, (2) se elige la variante y (3) según el producto:
 *
 * - Con prendas físicas (alquilables): se marcan las prendas por su código.
 * - Sin prendas físicas (solo venta): se indica la cantidad, con el stock como tope.
 *
 * Lo usan Venta y Alquiler. Nunca ofrece una combinación sin disponibilidad.
 * Dispara `product-picker-selected` con líneas listas para el carrito.
 */
class ProductPicker extends Component
{
    public bool $isOpen = false;

    public ?int $productId = null;

    public string $mode = 'sale'; // 'sale' | 'rental'

    public string $fieldContext = '';

    public ?int $variantId = null;

    /** @var array<int, int> */
    public array $selectedUnitIds = [];

    public int $quantity = 1;

    #[On('product-picker-requested')]
    public function open(string $context, int $productId, string $mode = 'sale'): void
    {
        $this->reset();

        $this->fieldContext = $context;
        $this->productId = $productId;
        $this->mode = $mode === 'rental' ? 'rental' : 'sale';

        // Si solo hay una variante con disponibilidad, se preselecciona.
        $withStock = $this->variants->where('available', '>', 0);
        $this->variantId = $withStock->count() === 1 ? $withStock->first()->id : null;

        $this->isOpen = true;
    }

    #[Computed]
    public function product(): ?Product
    {
        return $this->productId ? Product::with('category:id,name')->find($this->productId) : null;
    }

    /**
     * Si el producto se rastrea por prenda física (se elige por código).
     */
    #[Computed]
    public function tracksUnits(): bool
    {
        return (bool) $this->product?->allow_rental;
    }

    #[Computed]
    public function coverUrl(): ?string
    {
        return $this->productId
            ? ProductMedia::where('product_id', $this->productId)->orderByDesc('is_primary')->orderBy('sort_order')->first()?->url
            : null;
    }

    /**
     * Variantes con su disponibilidad: prendas disponibles si se rastrea por
     * prenda física, stock de la variante si no.
     */
    #[Computed]
    public function variants(): Collection
    {
        if (! $this->product) {
            return collect();
        }

        $variants = ProductVariant::where('product_id', $this->productId)
            ->where('is_active', true)
            ->with(['size', 'color'])
            ->get();

        $availableUnits = $this->tracksUnits
            ? PhysicalProduct::whereIn('product_variant_id', $variants->pluck('id'))
                ->where('status', 'available')
                ->select('product_variant_id', DB::raw('count(*) as units'))
                ->groupBy('product_variant_id')
                ->pluck('units', 'product_variant_id')
            : collect();

        return $variants
            ->each(fn (ProductVariant $variant) => $variant->available = $this->tracksUnits
                ? (int) ($availableUnits[$variant->id] ?? 0)
                : max(0, (int) $variant->stock))
            ->sortBy([fn ($v) => $v->size?->sort_order ?? 0, fn ($v) => $v->color?->name ?? ''])
            ->values();
    }

    #[Computed]
    public function selectedVariant(): ?ProductVariant
    {
        return $this->variants->firstWhere('id', $this->variantId);
    }

    /**
     * Prendas disponibles de la variante elegida, con cuántas veces se alquilaron.
     */
    #[Computed]
    public function units(): Collection
    {
        $variant = $this->selectedVariant;

        if (! $variant || ! $this->tracksUnits) {
            return collect();
        }

        return PhysicalProduct::where('product_variant_id', $variant->id)
            ->where('status', 'available')
            ->withCount('rentalDetails')
            ->orderBy('code')
            ->get()
            ->each(fn (PhysicalProduct $unit) => $unit->suggested_price = $this->unitPrice($unit, $variant));
    }

    public function selectVariant(int $variantId): void
    {
        $variant = $this->variants->firstWhere('id', $variantId);

        if (! $variant || $variant->available === 0) {
            return;
        }

        $this->variantId = $variantId;
        $this->selectedUnitIds = [];
        $this->quantity = 1;
        $this->resetValidation();
        unset($this->selectedVariant, $this->units);
    }

    public function toggleUnit(int $unitId): void
    {
        $this->selectedUnitIds = in_array($unitId, $this->selectedUnitIds)
            ? array_values(array_diff($this->selectedUnitIds, [$unitId]))
            : [...$this->selectedUnitIds, $unitId];
    }

    public function addSelected(): void
    {
        $variant = $this->selectedVariant;

        if (! $variant) {
            $this->addError('variantId', 'Elige una talla y color disponibles.');

            return;
        }

        $base = [
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'product_code' => $this->product->code,
            'category_id' => $this->product->category_id,
            'size_id' => $variant->size_id,
            'color_id' => $variant->color_id,
            'variant_label' => $variant->label(),
        ];

        if ($this->tracksUnits) {
            $units = $this->units->whereIn('id', $this->selectedUnitIds);

            if ($units->isEmpty()) {
                $this->addError('selectedUnitIds', 'Elige al menos una prenda.');

                return;
            }

            $items = $units->map(fn (PhysicalProduct $unit) => [
                ...$base,
                'physical_product_id' => $unit->id,
                'physical_code' => $unit->code,
                'quantity' => 1,
                'unit_price' => $unit->suggested_price,
            ])->values()->all();
        } else {
            $this->validate([
                'quantity' => ['required', 'integer', 'min:1', 'max:'.$variant->available],
            ], [
                'quantity.max' => "Solo hay {$variant->available} en stock para esta talla/color.",
            ], ['quantity' => 'cantidad']);

            $items = [[
                ...$base,
                'physical_product_id' => null,
                'physical_code' => null,
                'quantity' => $this->quantity,
                'unit_price' => (float) ($variant->sale_price ?? $this->product->price ?? 0),
            ]];
        }

        $this->dispatch('product-picker-selected', context: $this->fieldContext, items: $items);

        $this->isOpen = false;
    }

    /**
     * Precio sugerido de una prenda (en venta, con IGV incluido).
     */
    protected function unitPrice(PhysicalProduct $unit, ProductVariant $variant): float
    {
        $price = $this->mode === 'rental'
            ? ($unit->rental_price ?? $variant->rental_price)
            : ($unit->suggested_sale_price ?? $variant->sale_price ?? $this->product?->price);

        return (float) ($price ?? 0);
    }

    public function render(): View
    {
        return view('livewire.shared.product-picker');
    }
}
