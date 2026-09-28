<?php

namespace App\Livewire\Finance;

use App\Models\OperationalExpense;
use App\Services\Finance\ExpenseService;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class ExpenseForm extends Component
{
    public bool $isOpen = false;

    public string $category = '';

    public string $concept = '';

    public ?string $amount = null;

    public ?string $expense_date = null;

    public ?string $notes = null;

    #[On('open-expense-form')]
    public function open(): void
    {
        $this->resetValidation();
        $this->reset(['category', 'concept', 'amount', 'notes']);
        $this->expense_date = now()->format('Y-m-d\TH:i');
        $this->isOpen = true;
    }

    /**
     * Sugerencias para el input (libre texto): las categorías ya usadas,
     * las más frecuentes primero. No hay catálogo que administrar.
     */
    #[Computed]
    public function categories(): Collection
    {
        return OperationalExpense::query()
            ->select('category')
            ->groupBy('category')
            ->orderByRaw('count(*) desc')
            ->limit(50)
            ->pluck('category');
    }

    /**
     * Conceptos ya usados; si ya escribiste una categoría, solo los de esa categoría.
     */
    #[Computed]
    public function concepts(): Collection
    {
        $category = trim($this->category);

        return OperationalExpense::query()
            ->when($category !== '', fn ($q) => $q->where('category', $category))
            ->select('concept')
            ->groupBy('concept')
            ->orderByRaw('count(*) desc')
            ->limit(50)
            ->pluck('concept');
    }

    public function save(ExpenseService $service): void
    {
        // Fuera del try/catch para que Livewire muestre los errores en cada campo.
        $data = $this->validate([
            'category' => 'required|string|max:255',
            'concept' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01|max:99999999.99',
            'expense_date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
        ], [
            'category.required' => 'Escribe la categoría del gasto.',
            'concept.required' => 'Escribe el concepto del gasto.',
            'amount.required' => 'Indica el monto.',
            'amount.min' => 'El monto debe ser mayor a cero.',
        ]);

        try {
            $service->register($data);

            $this->isOpen = false;
            $this->dispatch('expense-saved');
            Flux::toast(variant: 'success', text: 'Gasto registrado correctamente.');
        } catch (\Throwable $th) {
            report($th);
            Flux::toast(variant: 'danger', text: $th->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.finance.expense-form');
    }
}
