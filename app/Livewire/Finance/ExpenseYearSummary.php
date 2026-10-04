<?php

namespace App\Livewire\Finance;

use App\Models\OperationalExpense;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Resumen anual (solo lectura): tabla de doble entrada categoría × mes
 * con su gráfico. Agrupa por el texto exacto de la categoría, así que un
 * error de tipeo aparece como fila aparte (se corrige eliminando y recreando).
 */
class ExpenseYearSummary extends Component
{
    public const MONTHS = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    public bool $isOpen = false;

    public int $year;

    public function mount(): void
    {
        $this->year = now()->year;
    }

    #[On('open-expense-summary')]
    public function open(): void
    {
        $this->year = now()->year;
        $this->isOpen = true;
    }

    /**
     * Años para el selector: desde el del gasto más antiguo hasta el actual (más reciente primero).
     */
    #[Computed]
    public function years(): array
    {
        $first = OperationalExpense::min('expense_date');
        $last = OperationalExpense::max('expense_date');

        $bottom = $first ? (int) date('Y', strtotime($first)) : now()->year;
        $top = max(now()->year, $last ? (int) date('Y', strtotime($last)) : now()->year);

        return range($top, min($bottom, $top));
    }

    /**
     * [categoría => ['months' => [1..12 => monto], 'total' => monto]], la de mayor gasto primero.
     */
    #[Computed]
    public function matrix(): Collection
    {
        return OperationalExpense::active()
            ->whereYear('expense_date', $this->year)
            ->get(['category', 'amount', 'expense_date'])
            ->groupBy('category')
            ->map(function (Collection $items) {
                $months = array_fill(1, 12, 0.0);

                foreach ($items as $expense) {
                    $months[$expense->expense_date->month] += (float) $expense->amount;
                }

                return ['months' => $months, 'total' => array_sum($months)];
            })
            ->sortByDesc('total');
    }

    #[Computed]
    public function monthlyTotals(): array
    {
        $totals = array_fill(1, 12, 0.0);

        foreach ($this->matrix as $row) {
            foreach ($row['months'] as $month => $amount) {
                $totals[$month] += $amount;
            }
        }

        return $totals;
    }

    #[Computed]
    public function grandTotal(): float
    {
        return array_sum($this->monthlyTotals);
    }

    public function render()
    {
        return view('livewire.finance.expense-year-summary', ['months' => self::MONTHS]);
    }
}
