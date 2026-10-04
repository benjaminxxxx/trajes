<?php

namespace App\Livewire\Finance;

use App\Models\OperationalExpense;
use App\Services\Finance\ExpenseService;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Gastos operativos')]
class ExpenseIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    public bool $showCancelModal = false;

    public ?int $expenseToCancel = null;

    public string $cancellationReason = '';

    public function mount(): void
    {
        // Por defecto, el mes en curso.
        $this->dateFrom = $this->dateFrom ?: now()->startOfMonth()->toDateString();
        $this->dateTo = $this->dateTo ?: now()->endOfMonth()->toDateString();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    // Al guardar un gasto desde el modal, este método hace que el listado se vuelva a renderizar.
    #[On('expense-saved')]
    public function refreshList(): void
    {
        $this->resetPage();
    }

    protected function baseQuery(): Builder
    {
        return OperationalExpense::query()
            ->when($this->search !== '', function (Builder $q) {
                $q->where(function (Builder $q2) {
                    $q2->where('category', 'like', "%{$this->search}%")
                        ->orWhere('concept', 'like', "%{$this->search}%");
                });
            })
            ->when($this->dateFrom !== '', fn (Builder $q) => $q->whereDate('expense_date', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn (Builder $q) => $q->whereDate('expense_date', '<=', $this->dateTo));
    }

    #[Computed]
    public function expenses(): LengthAwarePaginator
    {
        return $this->baseQuery()
            ->with('user', 'cancelledBy:id,name')
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->paginate(15);
    }

    #[Computed]
    public function periodTotal(): float
    {
        return (float) $this->baseQuery()->active()->sum('amount');
    }

    #[Computed]
    public function pendingCancellation(): ?OperationalExpense
    {
        return $this->expenseToCancel ? OperationalExpense::find($this->expenseToCancel) : null;
    }

    public function confirmCancel(int $id): void
    {
        $this->resetValidation();
        $this->expenseToCancel = $id;
        $this->cancellationReason = '';
        $this->showCancelModal = true;
    }

    /**
     * El gasto no se borra: queda anulado y su monto vuelve a caja como reingreso.
     */
    public function cancel(ExpenseService $service): void
    {
        $this->authorize('finanzas.gastos');

        $this->validate(
            ['cancellationReason' => ['required', 'string', 'min:3', 'max:255']],
            ['cancellationReason.required' => 'Indica el motivo de la anulación.'],
            ['cancellationReason' => 'motivo'],
        );

        try {
            $service->cancel(OperationalExpense::findOrFail($this->expenseToCancel), $this->cancellationReason);

            $this->showCancelModal = false;
            $this->expenseToCancel = null;
            Flux::toast(variant: 'success', text: 'Gasto anulado: su monto volvió a caja.');
        } catch (\Throwable $th) {
            report($th);
            Flux::toast(variant: 'danger', text: $th->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.finance.expense-index');
    }
}
