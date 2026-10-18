<?php

namespace App\Livewire\Inventory;

use App\Models\Attribute;
use App\Models\Category;
use App\Services\Core\AccountRule;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Características que se piden al registrar un producto (Tela, Corte, Cuero)
 * y sus sugerencias.
 *
 * Todo aquí es solo una SUGERENCIA: cada producto guarda su propio texto, así
 * que renombrar, desactivar o borrar algo no cambia ningún producto ya
 * registrado. Una sugerencia que ya no se usa se borra; la nueva se agrega.
 */
#[Title('Telas, cortes y más')]
class CharacteristicCatalog extends Component
{
    public bool $isFormOpen = false;

    public ?int $editingId = null;

    public string $name = '';

    public bool $is_active = true;

    /** @var array<int, int|string> */
    public array $category_ids = [];

    /** @var array<int, string> Sugerencia por escribir, por característica. */
    public array $newValues = [];

    public function create(): void
    {
        $this->authorize('inventario.atributos');
        $this->resetForm();
        $this->isFormOpen = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('inventario.atributos');
        $this->resetForm();

        $attribute = Attribute::with('categories:id')->findOrFail($id);

        $this->editingId = $attribute->id;
        $this->name = $attribute->name;
        $this->is_active = $attribute->is_active;
        $this->category_ids = $attribute->categories->pluck('id')->all();
        $this->isFormOpen = true;
    }

    public function save(): void
    {
        $this->authorize('inventario.atributos');

        $this->validate([
            'name' => ['required', 'string', 'max:60', AccountRule::unique('attributes', 'name')->ignore($this->editingId)],
            'is_active' => ['boolean'],
            'category_ids' => ['array'],
            'category_ids.*' => [AccountRule::exists('categories')],
        ], attributes: ['name' => 'nombre']);

        DB::transaction(function () {
            $attribute = $this->editingId ? Attribute::findOrFail($this->editingId) : new Attribute;
            $attribute->fill(['name' => Str::squish($this->name), 'is_active' => $this->is_active])->save();
            $attribute->categories()->sync(array_map('intval', $this->category_ids));
        });

        $this->isFormOpen = false;
        Flux::toast(variant: 'success', text: 'Característica guardada.');
    }

    public function toggleActive(int $id): void
    {
        $this->authorize('inventario.atributos');

        $attribute = Attribute::findOrFail($id);
        $attribute->update(['is_active' => ! $attribute->is_active]);

        Flux::toast(variant: 'success', text: $attribute->is_active
            ? "«{$attribute->name}» se volverá a pedir al registrar productos."
            : "«{$attribute->name}» ya no se pedirá. Los productos conservan su valor.");
    }

    public function delete(int $id): void
    {
        $this->authorize('inventario.atributos');

        $attribute = Attribute::findOrFail($id);
        $attribute->delete();

        Flux::toast(variant: 'success', text: "«{$attribute->name}» se eliminó. Los productos conservan su valor.");
    }

    public function addValue(int $attributeId): void
    {
        $this->authorize('inventario.atributos');

        $value = Str::squish((string) ($this->newValues[$attributeId] ?? ''));
        $this->newValues[$attributeId] = '';

        if ($value === '') {
            return;
        }

        $attribute = Attribute::findOrFail($attributeId);

        if ($attribute->values()->whereRaw('LOWER(value) = ?', [Str::lower($value)])->exists()) {
            Flux::toast(variant: 'warning', text: "«{$value}» ya está en las sugerencias.");

            return;
        }

        $attribute->values()->create(['value' => mb_substr($value, 0, 120)]);
    }

    public function removeValue(int $attributeId, int $valueId): void
    {
        $this->authorize('inventario.atributos');

        Attribute::findOrFail($attributeId)->values()->whereKey($valueId)->delete();
    }

    protected function resetForm(): void
    {
        $this->resetValidation();
        $this->reset(['editingId', 'name', 'is_active', 'category_ids']);
    }

    public function render(): View
    {
        return view('livewire.inventory.characteristic-catalog', [
            'characteristics' => Attribute::query()
                ->with(['values:id,attribute_id,value', 'categories:id,name'])
                ->withCount('productCharacteristics')
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(),
            'categories' => Category::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
