<?php

namespace App\Livewire\Inventory;

use App\Models\Asset;
use App\Models\Category;
use App\Services\Assets\AssetService;
use App\Services\Core\AccountRule;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Bienes del negocio: mobiliario, maniquíes, ganchos... y los consumibles que
 * se entregan con la venta (portaternos, correas). Con precio de venta, un
 * bien también se puede vender desde el panel de ventas. Suman al valorizado
 * de la empresa. Solo administradores.
 */
#[Title('Bienes del negocio')]
class AssetIndex extends Component
{
    #[Url(history: true)]
    public string $search = '';

    #[Url(as: 'estado', history: true)]
    public string $status = Asset::STATUS_ACTIVE;

    public bool $showForm = false;

    public bool $showStock = false;

    public bool $showDischarge = false;

    public bool $showHistory = false;

    public ?int $assetId = null;

    // Formulario del bien
    public string $name = '';

    public string $type = 'mobiliario';

    public int|string $quantity = 1;

    public float|string $unit_cost = '';

    public float|string $current_unit_value = '';

    // Precio de venta con IGV; vacío = el bien no se vende.
    public float|string|null $sale_price = '';

    // La compra se paga desde caja (genera el egreso).
    public bool $paidFromCash = true;

    public bool $is_consumable = false;

    public bool $delivers_with_sale = false;

    /** @var array<int, int|string> */
    public array $category_ids = [];

    public ?string $notes = null;

    // Ingreso y baja
    public int|string $movementQuantity = 1;

    public float|string $movementCost = '';

    public string $movementNote = '';

    public function create(): void
    {
        $this->authorize('inventario.bienes');
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('inventario.bienes');
        $this->resetForm();

        $asset = Asset::with('categories:id')->findOrFail($id);

        $this->assetId = $asset->id;
        $this->name = $asset->name;
        $this->type = $asset->type;
        $this->quantity = $asset->quantity;
        $this->unit_cost = round((float) $asset->unit_cost, 2);
        $this->current_unit_value = round((float) $asset->current_unit_value, 2);
        $this->sale_price = $asset->sale_price === null ? '' : (float) $asset->sale_price;
        $this->is_consumable = $asset->is_consumable;
        $this->delivers_with_sale = $asset->delivers_with_sale;
        $this->category_ids = $asset->categories->pluck('id')->all();
        $this->notes = $asset->notes;
        $this->showForm = true;
    }

    public function save(AssetService $service): void
    {
        $this->authorize('inventario.bienes');

        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(array_keys(Asset::TYPES))],
            'quantity' => [$this->assetId ? 'nullable' : 'required', 'integer', 'min:0', 'max:100000'],
            'unit_cost' => [$this->assetId ? 'nullable' : 'required', 'numeric', 'min:0', 'max:9999999'],
            'current_unit_value' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'sale_price' => ['nullable', 'numeric', 'min:0.01', 'max:9999999'],
            'category_ids' => ['array'],
            'category_ids.*' => ['integer', AccountRule::exists('categories')],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $deliversWithSale = $this->is_consumable && $this->delivers_with_sale;
        $categoryIds = $deliversWithSale ? array_map('intval', $this->category_ids) : [];
        $currentValue = $this->current_unit_value === '' ? null : (float) $this->current_unit_value;
        $salePrice = in_array($this->sale_price, ['', null], true) ? null : round((float) $this->sale_price, 2);

        if (! $this->assetId) {
            $service->create([
                'name' => trim($this->name),
                'type' => $this->type,
                'quantity' => (int) $this->quantity,
                'unit_cost' => (float) $this->unit_cost,
                'current_unit_value' => $currentValue,
                'sale_price' => $salePrice,
                'is_consumable' => $this->is_consumable,
                'delivers_with_sale' => $deliversWithSale,
                'notes' => $this->notes,
            ], $categoryIds, $this->paidFromCash);

            $this->showForm = false;
            Flux::toast(variant: 'success', text: "«{$this->name}» se registró.");

            return;
        }

        // La cantidad y el costo de compra solo cambian con ingresos y bajas,
        // para que el historial del bien cuadre.
        DB::transaction(function () use ($service, $deliversWithSale, $categoryIds, $currentValue, $salePrice) {
            $asset = Asset::findOrFail($this->assetId);

            $asset->update([
                'name' => trim($this->name),
                'type' => $this->type,
                'sale_price' => $salePrice,
                'is_consumable' => $this->is_consumable,
                'delivers_with_sale' => $deliversWithSale,
                'notes' => $this->notes,
            ]);
            $asset->categories()->sync($categoryIds);

            if ($currentValue !== null && round($currentValue, 4) !== round((float) $asset->current_unit_value, 4)) {
                $service->revalue($asset, $currentValue);
            }
        });

        $this->showForm = false;
        Flux::toast(variant: 'success', text: 'Cambios guardados.');
    }

    public function openStock(int $id): void
    {
        $this->authorize('inventario.bienes');
        $this->resetMovement($id);
        $this->movementCost = round((float) Asset::findOrFail($id)->unit_cost, 2);
        $this->showStock = true;
    }

    public function addStock(AssetService $service): void
    {
        $this->authorize('inventario.bienes');

        $this->validate([
            'movementQuantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'movementCost' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'movementNote' => ['nullable', 'string', 'max:255'],
        ]);

        $asset = $service->addStock(Asset::findOrFail($this->assetId), (int) $this->movementQuantity, (float) $this->movementCost, $this->movementNote ?: null, $this->paidFromCash);

        $this->showStock = false;
        Flux::toast(variant: 'success', text: "Ahora hay {$asset->quantity} de «{$asset->name}».");
    }

    public function openDischarge(int $id): void
    {
        $this->authorize('inventario.bienes');
        $this->resetMovement($id);
        $this->showDischarge = true;
    }

    public function discharge(AssetService $service): void
    {
        $this->authorize('inventario.bienes');

        $asset = Asset::findOrFail($this->assetId);

        $this->validate([
            'movementQuantity' => ['required', 'integer', 'min:1', 'max:'.$asset->quantity],
            'movementNote' => ['required', 'string', 'max:255'],
        ], [
            'movementQuantity.max' => "Solo hay {$asset->quantity} unidad(es).",
            'movementNote.required' => 'Indica el motivo (ej. malogrado).',
        ]);

        $service->discharge($asset, (int) $this->movementQuantity, $this->movementNote);

        $this->showDischarge = false;
        Flux::toast(variant: 'success', text: "Se dio de baja {$this->movementQuantity} de «{$asset->name}».");
    }

    public function openHistory(int $id): void
    {
        $this->authorize('inventario.bienes');
        $this->assetId = $id;
        $this->showHistory = true;
    }

    /**
     * Eliminación lógica: queda en audit_logs quién la hizo y cuándo.
     */
    public function delete(int $id): void
    {
        $this->authorize('inventario.bienes');

        $asset = Asset::findOrFail($id);
        $asset->delete();

        Flux::toast(variant: 'success', text: "«{$asset->name}» se eliminó (queda registrado quién lo hizo).");
    }

    protected function resetForm(): void
    {
        $this->resetValidation();
        $this->reset(['assetId', 'name', 'type', 'quantity', 'unit_cost', 'current_unit_value', 'sale_price', 'paidFromCash', 'is_consumable', 'delivers_with_sale', 'category_ids', 'notes']);
    }

    protected function resetMovement(int $id): void
    {
        $this->resetValidation();
        $this->reset(['movementQuantity', 'movementCost', 'movementNote', 'paidFromCash']);
        $this->assetId = $id;
    }

    public function render(): View
    {
        $assets = Asset::query()
            ->with('categories:id,name')
            ->when(filled($this->search), fn ($query) => $query->where('name', 'like', '%'.trim($this->search).'%'))
            ->when($this->status !== 'todos', fn ($query) => $query->where('status', $this->status))
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $selected = $this->assetId ? Asset::withTrashed()->find($this->assetId) : null;

        return view('livewire.inventory.asset-index', [
            'assets' => $assets,
            'totals' => [
                'cost' => $assets->sum(fn (Asset $asset) => $asset->totalCost()),
                'value' => $assets->sum(fn (Asset $asset) => $asset->totalValue()),
            ],
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'selected' => $selected,
            'history' => $this->showHistory && $selected
                ? $selected->movements()->with('user:id,name')->limit(50)->get()
                : collect(),
        ]);
    }
}
