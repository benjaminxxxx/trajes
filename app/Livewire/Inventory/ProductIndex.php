<?php

namespace App\Livewire\Inventory;

use App\Models\Category;
use App\Models\Product;
use App\Services\Inventory\ProductSearch;
use App\Services\Inventory\ProductService;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class ProductIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public ?int $categoryFilter = null;

    public bool $showTrashed = false;

    public function mount(): void
    {
        $this->categoryFilter = request()->integer('category') ?: null;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function updatingShowTrashed(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->dispatch('open-product-modal');
    }

    public function edit(int $productId): void
    {
        $this->dispatch('open-product-modal', productId: $productId);
    }

    public function manageMedia(int $productId): void
    {
        $this->dispatch('manageMedia', productId: $productId);
    }

    /**
     * El modal de producto guardó: basta con volver a renderizar la lista.
     */
    #[On('product-saved')]
    public function refreshProducts(): void {}

    public function delete(int $productId, ProductService $service): void
    {
        $product = Product::findOrFail($productId);
        $service->eliminar($product);
    }

    public function restore(int $productId, ProductService $service): void
    {
        $product = Product::onlyTrashed()->findOrFail($productId);
        $service->restaurar($product);
    }

    public function render()
    {
        $products = Product::query()
            ->with([
                'category:id,name',
                'brand:id,name',
                'characteristics',
                'media' => fn ($q) => $q->where('is_primary', true),
            ])
            ->withCount('physicalProducts')
            ->when($this->showTrashed, fn ($q) => $q->onlyTrashed())
            ->when($this->categoryFilter, fn ($q) => $q->where('category_id', $this->categoryFilter))
            ->when(trim($this->search) !== '', fn ($q) => app(ProductSearch::class)->apply($q, $this->search))
            ->orderBy('name')
            ->paginate(12);

        return view('livewire.inventory.product-index', [
            'products' => $products,
            'categoryOptions' => Category::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
