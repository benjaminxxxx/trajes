<?php

namespace App\Livewire\Inventory;

use App\Models\Asset;
use App\Models\DepreciationRule;
use App\Models\PhysicalProduct;
use App\Services\Inventory\ValuationService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Valorizado de la empresa (ej. para vender el negocio): lo que costó y lo
 * que vale hoy cada prenda de alquiler (con desgaste), el stock de venta y
 * los bienes.
 */
#[Title('Valorizado de la empresa')]
class CompanyValuation extends Component
{
    use WithPagination;

    public const TABS = ['prendas', 'stock', 'bienes'];

    #[Url(history: true)]
    public string $tab = 'prendas';

    #[Url(history: true)]
    public string $search = '';

    public function updatedTab(): void
    {
        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'prendas';
        }

        $this->search = '';
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function summary(): array
    {
        return app(ValuationService::class)->summary();
    }

    public function render(ValuationService $valuation): View
    {
        $term = trim($this->search);

        $rows = match ($this->tab) {
            'stock' => $valuation->saleStockQuery()
                ->with(['product:id,name,code', 'size:id,name', 'color:id,name'])
                ->when($term !== '', fn ($query) => $query->whereHas('product', fn ($product) => $product
                    ->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%")))
                ->orderByDesc('stock')
                ->paginate(25),
            'bienes' => $valuation->assetsQuery()
                ->when($term !== '', fn ($query) => $query->where('name', 'like', "%{$term}%"))
                ->orderBy('name')
                ->paginate(25),
            default => $valuation->garmentsQuery()
                ->with(['variant.product:id,name', 'variant.size:id,name', 'variant.color:id,name'])
                ->when($term !== '', fn ($query) => $query->where(fn ($inner) => $inner
                    ->where('code', 'like', "%{$term}%")
                    ->orWhereHas('variant.product', fn ($product) => $product->where('name', 'like', "%{$term}%"))))
                ->orderByDesc('rentals_count')
                ->orderBy('code')
                ->paginate(25)
                ->through(fn (PhysicalProduct $unit) => $valuation->valueGarment($unit)),
        };

        return view('livewire.inventory.company-valuation', [
            'rows' => $rows,
            'rules' => DepreciationRule::orderBy('min_rentals')->get(),
            'assetTypes' => Asset::TYPES,
        ]);
    }
}
