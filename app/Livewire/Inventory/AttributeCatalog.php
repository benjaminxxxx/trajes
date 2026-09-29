<?php

namespace App\Livewire\Inventory;

use App\Models\Brand;
use App\Models\Color;
use App\Models\Size;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Administración de tallas, colores y marcas.
 *
 * Regla para no alterar el historial: si una talla/color/marca ya está en uso
 * por algún producto, su NOMBRE no se puede cambiar. En su lugar se desactiva
 * (deja de ofrecerse en categorías, compras y productos) y se crea la nueva.
 * Las que nunca se usaron se pueden editar o borrar libremente.
 */
#[Title('Tallas, colores y marcas')]
class AttributeCatalog extends Component
{
    public const TABS = [
        'tallas' => Size::class,
        'colores' => Color::class,
        'marcas' => Brand::class,
    ];

    #[Url(history: true)]
    public string $tab = 'tallas';

    public string $search = '';

    public bool $isFormOpen = false;

    public ?int $editingId = null;

    public bool $nameLocked = false;

    public string $name = '';

    public string $type = 'estandar';

    public int $sort_order = 0;

    public ?string $hex_code = '#000000';

    public bool $is_active = true;

    public function mount(): void
    {
        if (! array_key_exists($this->tab, self::TABS)) {
            $this->tab = 'tallas';
        }
    }

    public function updatedTab(): void
    {
        if (! array_key_exists($this->tab, self::TABS)) {
            $this->tab = 'tallas';
        }

        $this->search = '';
    }

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

        $record = $this->findRecord($id);

        $this->editingId = $record->id;
        $this->name = $record->name;
        $this->is_active = (bool) $record->is_active;
        $this->nameLocked = $record->isInUse();

        if ($record instanceof Size) {
            $this->type = $record->type;
            $this->sort_order = (int) $record->sort_order;
        }

        if ($record instanceof Color) {
            $this->hex_code = $record->hex_code ?? '#000000';
        }

        $this->isFormOpen = true;
    }

    public function save(): void
    {
        $this->authorize('inventario.atributos');

        $record = $this->editingId ? $this->findRecord($this->editingId) : null;

        // El nombre de algo en uso no cambia, aunque alguien lo envíe manipulado.
        if ($record && $record->isInUse()) {
            $this->name = $record->name;
        }

        $this->validate($this->rules($record));

        $data = ['name' => trim($this->name), 'is_active' => $this->is_active];

        match ($this->tab) {
            'tallas' => $data += ['type' => trim($this->type), 'sort_order' => $this->sort_order],
            'colores' => $data += ['hex_code' => $this->hex_code],
            'marcas' => $data += ['slug' => Str::slug($this->name)],
        };

        if ($record) {
            $record->update($data);
        } else {
            (self::TABS[$this->tab])::create($data);
        }

        $this->isFormOpen = false;
        Flux::toast(variant: 'success', text: 'Guardado correctamente.');
    }

    public function toggleActive(int $id): void
    {
        $this->authorize('inventario.atributos');

        $record = $this->findRecord($id);
        $record->update(['is_active' => ! $record->is_active]);

        Flux::toast(variant: 'success', text: $record->is_active ? "«{$record->name}» se volvió a activar." : "«{$record->name}» se desactivó: ya no se ofrecerá.");
    }

    public function delete(int $id): void
    {
        $this->authorize('inventario.atributos');

        $record = $this->findRecord($id);

        if ($record->isInUse()) {
            Flux::toast(variant: 'danger', text: "«{$record->name}» está en uso por productos: desactívala en lugar de borrarla.");

            return;
        }

        $record->delete();
        Flux::toast(variant: 'success', text: "«{$record->name}» se eliminó.");
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(?Model $record): array
    {
        $table = (new (self::TABS[$this->tab]))->getTable();
        $unique = Rule::unique($table, 'name')->ignore($record?->id);

        return match ($this->tab) {
            'tallas' => [
                'name' => ['required', 'string', 'max:20', $unique->where('type', trim($this->type))],
                'type' => ['required', 'string', 'max:40'],
                'sort_order' => ['integer', 'min:0', 'max:9999'],
                'is_active' => ['boolean'],
            ],
            'colores' => [
                'name' => ['required', 'string', 'max:60', $unique],
                'hex_code' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
                'is_active' => ['boolean'],
            ],
            'marcas' => [
                'name' => [
                    'required', 'string', 'max:80', $unique,
                    // Dos nombres distintos pueden dar el mismo slug ("Zara" y "ZARA").
                    function (string $attribute, mixed $value, \Closure $fail) use ($record) {
                        if (Brand::where('slug', Str::slug($value))->whereKeyNot($record?->id)->exists()) {
                            $fail('Ya existe una marca con ese nombre.');
                        }
                    },
                ],
                'is_active' => ['boolean'],
            ],
        };
    }

    protected function findRecord(int $id): Size|Color|Brand
    {
        return (self::TABS[$this->tab])::findOrFail($id);
    }

    protected function resetForm(): void
    {
        $this->resetValidation();
        $this->reset(['editingId', 'nameLocked', 'name', 'type', 'sort_order', 'hex_code', 'is_active']);
    }

    public function render(): View
    {
        $model = self::TABS[$this->tab];
        $usage = match ($this->tab) {
            'marcas' => ['products' => fn ($q) => $q->withTrashed()],
            default => ['variants', 'categories'],
        };

        $records = $model::query()
            ->withCount($usage)
            ->when(filled($this->search), fn ($q) => $q->where('name', 'like', '%'.trim($this->search).'%'))
            ->when($this->tab === 'tallas', fn ($q) => $q->orderBy('type')->orderBy('sort_order'))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return view('livewire.inventory.attribute-catalog', [
            'records' => $records,
            'sizeTypes' => Size::TYPES + Size::query()->distinct()->pluck('type', 'type')->all(),
            'counts' => [
                'tallas' => Size::count(),
                'colores' => Color::count(),
                'marcas' => Brand::count(),
            ],
        ]);
    }
}
