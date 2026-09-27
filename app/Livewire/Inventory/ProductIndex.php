<?php

namespace App\Livewire\Inventory;

use App\Models\Category;
use App\Models\Product;
use App\Services\Inventory\ProductService;
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
        $this->redirectRoute('inventory.products.create');
    }

    public function edit(int $productId): void
    {
        $this->redirectRoute('inventory.products.edit', ['product' => $productId]);
    }

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
                'media' => fn($q) => $q->where('is_primary', true)
            ])
            ->withCount('physicalProducts')
            ->when($this->showTrashed, fn($q) => $q->onlyTrashed())
            ->when($this->categoryFilter, fn($q) => $q->where('category_id', $this->categoryFilter))
            ->when($this->search !== '', function ($q) {
                $q->where(function ($sub) {
                    $sub->where('name', 'like', "%{$this->search}%")
                        ->orWhere('code', 'like', "%{$this->search}%");
                });
            })
            ->orderBy('name')
            ->paginate(12);

        return view('livewire.inventory.product-index', [
            'products' => $products,
            'categoryOptions' => Category::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}