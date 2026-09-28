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

    public bool $showDeleteModal = false;

    public ?int $expenseToDelete = null;

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
            ->with('user')
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->paginate(15);
    }

    #[Computed]
    public function periodTotal(): float
    {
        return (float) $this->baseQuery()->sum('amount');
    }

    #[Computed]
    public function pendingDeletion(): ?OperationalExpense
    {
        return $this->expenseToDelete ? OperationalExpense::find($this->expenseToDelete) : null;
    }

    public function confirmDelete(int $id): void
    {
        $this->expenseToDelete = $id;
        $this->showDeleteModal = true;
    }

    public function delete(ExpenseService $service): void
    {
        try {
            $expense = OperationalExpense::findOrFail($this->expenseToDelete);

            $service->delete($expense);

            $this->showDeleteModal = false;
            $this->expenseToDelete = null;
            Flux::toast(variant: 'success', text: 'Gasto eliminado. Quedó registrado en la auditoría.');
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
