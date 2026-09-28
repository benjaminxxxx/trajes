<?php

namespace App\Livewire\Inventory;

use App\Models\Category;
use App\Services\Inventory\CategoryReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Reporte anual (solo lectura): tabla de doble entrada Alquileres/Ventas × mes
 * en unidades, su gráfico, y el total de productos por categoría.
 */
class CategoryYearReport extends Component
{
    public const MONTHS = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    public bool $isOpen = false;

    public int $year;

    public ?int $categoryId = null;

    public function mount(): void
    {
        $this->year = now()->year;
    }

    #[On('open-category-report')]
    public function open(?int $categoryId = null): void
    {
        $this->year = now()->year;
        $this->categoryId = $categoryId;
        $this->isOpen = true;
    }

    #[Computed]
    public function years(): array
    {
        return app(CategoryReportService::class)->years();
    }

    #[Computed]
    public function categoryOptions(): Collection
    {
        return Category::orderBy('name')->get(['id', 'name']);
    }

    /**
     * @return array{rentals: array<int, int>, sales: array<int, int>}
     */
    #[Computed]
    public function matrix(): array
    {
        return app(CategoryReportService::class)->monthlyMatrix($this->year, $this->categoryId);
    }

    /**
     * Categorías con su total de productos y unidades alquiladas/vendidas en el año.
     *
     * @return Collection<int, array{name: string, products: int, rentals: int, sales: int}>
     */
    #[Computed]
    public function categorySummary(): Collection
    {
        $units = app(CategoryReportService::class)->unitsByCategory($this->year);

        return Category::withCount('products')
            ->orderByDesc('products_count')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Category $category) => [
                'name' => $category->name,
                'products' => $category->products_count,
                'rentals' => $units['rentals'][$category->id] ?? 0,
                'sales' => $units['sales'][$category->id] ?? 0,
            ]);
    }

    public function render(): View
    {
        return view('livewire.inventory.category-year-report', ['months' => self::MONTHS]);
    }
}
