<?php

namespace App\Livewire\Inventory;

use App\Models\Category;
use App\Services\Inventory\CategoryReportService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class CategoryIndex extends Component
{
    use WithPagination;

    #[Url(history: true)]
    public string $search = '';

    #[Url(history: true)]
    public bool $showArchived = false;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingShowArchived(): void
    {
        $this->resetPage();
    }

    #[On('categorySaved')]
    public function refreshList(): void
    {
        //
    }

    public function openCreateModal(): void
    {
        $this->dispatch('open-category-modal');
    }

    public function openEditModal(int $id): void
    {
        $this->dispatch('open-category-modal', id: $id);
    }

    public function restoreCategory(int $id): void
    {
        $this->authorize('inventario.categorias');

        Category::onlyTrashed()->findOrFail($id)->restore();

        Flux::toast(variant: 'success', text: 'Categoría restaurada correctamente.');
    }

    public function render(CategoryReportService $reports): View
    {
        $query = $this->showArchived
            ? Category::onlyTrashed()
            : Category::query();

        if (! empty($this->search)) {
            $query->where('name', 'like', '%'.trim($this->search).'%');
        }

        $categories = $query
            ->with(['parent:id,name', 'sizes:id,name', 'colors:id,name,hex_code'])
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(12);

        return view('livewire.inventory.category-index', [
            'categories' => $categories,
            'units' => $reports->unitsByCategory(),
        ]);
    }
}
