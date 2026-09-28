<?php

namespace App\Livewire\Inventory;

use App\Models\PhysicalProduct;
use App\Models\Product;
use App\Services\Inventory\ItemTrackingService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Seguimiento de prendas: un buscador de productos y prendas físicas.
 * - Prenda física: su historia completa (compra, alquileres, venta o baja).
 * - Producto: estadísticas del año elegido por mes, talla y color.
 */
#[Title('Seguimiento de prendas')]
class PhysicalItemTracker extends Component
{
    public const MONTHS = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    public string $search = '';

    #[Url(as: 'producto', history: true)]
    public ?int $productId = null;

    #[Url(as: 'prenda', history: true)]
    public ?int $unitId = null;

    #[Url(as: 'anio', history: true)]
    public ?int $year = null;

    public function mount(): void
    {
        $this->year ??= now()->year;
    }

    public function selectProduct(int $productId): void
    {
        $this->productId = $productId;
        $this->unitId = null;
        $this->year = now()->year;
        $this->search = '';
    }

    public function selectUnit(int $unitId): void
    {
        $this->unitId = $unitId;
        $this->productId = null;
        $this->search = '';
    }

    /**
     * Saca de mantenimiento la prenda abierta (mientras no exista el módulo
     * de lavandería, es la forma de devolverla al inventario disponible).
     */
    public function markUnitAvailable(): void
    {
        $this->authorize('mantenimiento.registrar');

        $unit = PhysicalProduct::whereKey($this->unitId)->where('status', 'maintenance')->firstOrFail();
        $unit->update(['status' => 'available', 'status_note' => null]);

        unset($this->unitHistory);
        Flux::toast(variant: 'success', text: "La prenda {$unit->code} vuelve a estar disponible.");
    }

    public function clearSelection(): void
    {
        $this->reset(['productId', 'unitId']);
    }

    #[Computed]
    public function results(): array
    {
        return app(ItemTrackingService::class)->search($this->search);
    }

    #[Computed]
    public function unitHistory(): ?array
    {
        $unit = $this->unitId ? PhysicalProduct::withTrashed()->find($this->unitId) : null;

        return $unit ? app(ItemTrackingService::class)->unitHistory($unit) : null;
    }

    #[Computed]
    public function product(): ?Product
    {
        return $this->productId ? Product::with('category:id,name')->find($this->productId) : null;
    }

    #[Computed]
    public function productStats(): ?array
    {
        if (! $this->product) {
            return null;
        }

        $service = app(ItemTrackingService::class);
        $years = $service->years($this->product);
        $year = in_array($this->year, $years) ? $this->year : $years[0];

        return [
            'year' => $year,
            'years' => $years,
            'stats' => $service->productYearStats($this->product, $year),
            'stock' => $service->currentStock($this->product),
            'units' => $this->product->allow_rental ? $service->productUnits($this->product) : collect(),
        ];
    }

    public function render(): View
    {
        return view('livewire.inventory.physical-item-tracker', ['months' => self::MONTHS]);
    }
}
