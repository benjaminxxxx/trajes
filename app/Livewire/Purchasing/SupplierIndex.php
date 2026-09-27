<?php

namespace App\Livewire\Purchasing;

use App\Models\Supplier;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class SupplierIndex extends Component
{
    use WithPagination;

    public string $search = '';

    #[On('supplier-saved')]
    public function refreshList(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->dispatch('open-supplier-modal');
    }

    public function openEditModal(int $id): void
    {
        $this->dispatch('open-supplier-modal', supplierId: $id);
    }

    public function delete(int $id): void
    {
        Supplier::findOrFail($id)->delete();
    }

    public function render()
    {
        $suppliers = Supplier::query()
            ->when($this->search, function ($query) {
                $query->where('display_name', 'like', '%' . $this->search . '%')
                    ->orWhere('document_number', 'like', '%' . $this->search . '%')
                    ->orWhere('code', 'like', '%' . $this->search . '%');
            })
            ->latest()
            ->paginate(15);

        return view('livewire.purchasing.supplier-index', [
            'suppliers' => $suppliers,
        ]);
    }
}