<?php

namespace App\Livewire\Inventory;

use App\Models\Category;
use App\Models\Color;
use App\Models\Size;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class CategoryFormModal extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public ?int $parent_id = null;

    public ?string $description = null;

    public int $sort_order = 0;

    public bool $is_active = true;

    /** @var array<int, int> */
    public array $selectedSizes = [];

    /** @var array<int, int> */
    public array $selectedColors = [];

    public int $productsCount = 0;

    public bool $isCategoryFormOpen = false;

    #[On('open-category-modal')]
    public function loadCategory(?int $id = null): void
    {
        $this->resetValidation();
        $this->reset();

        if ($id) {
            $category = Category::with(['sizes:id', 'colors:id'])->withCount('products')->findOrFail($id);

            $this->editingId = $category->id;
            $this->name = $category->name;
            $this->parent_id = $category->parent_id;
            $this->description = $category->description;
            $this->sort_order = $category->sort_order;
            $this->is_active = $category->is_active;
            $this->selectedSizes = $category->sizes->pluck('id')->all();
            $this->selectedColors = $category->colors->pluck('id')->all();
            $this->productsCount = $category->products_count;
        }

        $this->isCategoryFormOpen = true;
    }

    public function toggleSize(int $sizeId): void
    {
        $this->selectedSizes = in_array($sizeId, $this->selectedSizes)
            ? array_values(array_diff($this->selectedSizes, [$sizeId]))
            : [...$this->selectedSizes, $sizeId];
    }

    public function toggleColor(int $colorId): void
    {
        $this->selectedColors = in_array($colorId, $this->selectedColors)
            ? array_values(array_diff($this->selectedColors, [$colorId]))
            : [...$this->selectedColors, $colorId];
    }

    public function save(): void
    {
        $this->authorize('inventario.categorias');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', Rule::exists('categories', 'id')->whereNull('deleted_at'), Rule::notIn(array_filter([$this->editingId]))],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
            'selectedSizes' => ['array'],
            'selectedSizes.*' => ['integer', 'exists:sizes,id'],
            'selectedColors' => ['array'],
            'selectedColors.*' => ['integer', 'exists:colors,id'],
        ], [], [
            'parent_id' => 'categoría padre',
        ]);

        $slug = Str::slug($validated['name']);

        $slugTaken = Category::withTrashed()
            ->where('slug', $slug)
            ->when($this->editingId, fn ($q) => $q->whereKeyNot($this->editingId))
            ->exists();

        if ($slugTaken) {
            $this->addError('name', 'Ya existe una categoría con este nombre (revisa también las dadas de baja).');

            return;
        }

        DB::transaction(function () use ($validated, $slug) {
            $category = Category::updateOrCreate(
                ['id' => $this->editingId],
                [
                    'name' => trim($validated['name']),
                    'slug' => $slug,
                    'parent_id' => $validated['parent_id'],
                    'description' => $validated['description'],
                    'sort_order' => $validated['sort_order'],
                    'is_active' => $validated['is_active'],
                ]
            );

            $category->sizes()->sync($validated['selectedSizes']);
            $category->colors()->sync($validated['selectedColors']);
        });

        $this->isCategoryFormOpen = false;
        $this->dispatch('categorySaved');
        Flux::toast(variant: 'success', text: 'Categoría guardada correctamente.');
    }

    /**
     * Categories that already have products are soft deleted to keep the catalog
     * history intact; empty categories are removed permanently.
     */
    public function deleteCategory(): void
    {
        $this->authorize('inventario.categorias');

        if (! $this->editingId) {
            return;
        }

        $category = Category::findOrFail($this->editingId);

        if ($category->products()->withTrashed()->exists()) {
            $category->delete();
            Flux::toast(variant: 'success', text: 'La categoría tiene productos, fue dada de baja.');
        } else {
            $category->forceDelete();
            Flux::toast(variant: 'success', text: 'Categoría eliminada permanentemente.');
        }

        $this->isCategoryFormOpen = false;
        $this->dispatch('categorySaved');
    }

    public function render(): View
    {
        if (! $this->isCategoryFormOpen) {
            return view('livewire.inventory.category-form-modal', [
                'parentOptions' => collect(),
                'sizesByType' => collect(),
                'colors' => collect(),
            ]);
        }

        return view('livewire.inventory.category-form-modal', [
            'parentOptions' => Category::query()
                ->when($this->editingId, fn ($q) => $q->whereKeyNot($this->editingId))
                ->orderBy('name')
                ->get(['id', 'name']),
            'sizesByType' => Size::orderBy('sort_order')->get(['id', 'name', 'type'])->groupBy('type'),
            'colors' => Color::orderBy('name')->get(['id', 'name', 'hex_code']),
        ]);
    }
}
