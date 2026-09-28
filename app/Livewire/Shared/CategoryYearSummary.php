<?php

namespace App\Livewire\Shared;

use App\Services\Inventory\CategoryReportService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Resumen anual (solo lectura) de ventas o alquileres: tabla de doble entrada
 * categoría × mes, en unidades o en importe, con su gráfico mensual.
 * Las operaciones anuladas no se cuentan.
 */
class CategoryYearSummary extends Component
{
    public const MONTHS = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    /** @var 'sales'|'rentals' */
    #[Locked]
    public string $type = 'sales';

    public bool $isOpen = false;

    public int $year;

    /** @var 'units'|'amount' */
    public string $metric = 'units';

    public function mount(string $type): void
    {
        abort_unless(in_array($type, ['sales', 'rentals']), 404);

        $this->type = $type;
        $this->year = now()->year;
    }

    #[On('open-category-summary')]
    public function open(): void
    {
        $this->year = now()->year;
        $this->isOpen = true;
    }

    public function updatedMetric(string $value): void
    {
        if (! in_array($value, ['units', 'amount'])) {
            $this->metric = 'units';
        }
    }

    #[Computed]
    public function years(): array
    {
        return app(CategoryReportService::class)->years();
    }

    /**
     * @return array<string, array{months: array<int, float>, total: float}>
     */
    #[Computed]
    public function matrix(): array
    {
        return app(CategoryReportService::class)->categoryMonthMatrix($this->type, $this->year, $this->metric);
    }

    /**
     * @return array<int, float>
     */
    #[Computed]
    public function monthlyTotals(): array
    {
        $totals = array_fill(1, 12, 0.0);

        foreach ($this->matrix as $row) {
            foreach ($row['months'] as $month => $value) {
                $totals[$month] += $value;
            }
        }

        return $totals;
    }

    public function render(): View
    {
        return view('livewire.shared.category-year-summary', [
            'months' => self::MONTHS,
            'label' => $this->type === 'rentals' ? 'Alquileres' : 'Ventas',
        ]);
    }
}
