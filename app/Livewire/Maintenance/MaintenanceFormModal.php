<?php

namespace App\Livewire\Maintenance;

use App\Models\ItemMaintenance;
use App\Models\PhysicalProduct;
use App\Services\Maintenance\MaintenanceService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Registrar el costo de un mantenimiento (lavado, arreglo, planchado) de una
 * prenda física puntual, buscada por su código.
 */
class MaintenanceFormModal extends Component
{
    public bool $isOpen = false;

    public string $search = '';

    public ?int $unitId = null;

    public string $type = 'laundromat';

    public string $cost = '';

    public ?string $provider_name = null;

    public ?string $description = null;

    public string $maintenance_date = '';

    public string $after_status = 'keep';

    #[On('open-maintenance-modal')]
    public function open(?int $unitId = null): void
    {
        $this->authorize('mantenimiento.registrar');
        $this->resetValidation();
        $this->reset();
        unset($this->unit);

        $this->maintenance_date = now()->toDateString();

        if ($unitId) {
            $this->selectUnit($unitId);
        }

        $this->isOpen = true;
    }

    #[Computed]
    public function results(): Collection
    {
        if (mb_strlen(trim($this->search)) < 2) {
            return collect();
        }

        return PhysicalProduct::with(['variant.product:id,name', 'variant.size:id,name', 'variant.color:id,name'])
            ->where('code', 'like', '%'.trim($this->search).'%')
            ->orderBy('code')
            ->limit(6)
            ->get();
    }

    #[Computed]
    public function unit(): ?PhysicalProduct
    {
        return $this->unitId
            ? PhysicalProduct::withTrashed()->with(['variant.product:id,name', 'variant.size:id,name', 'variant.color:id,name'])->find($this->unitId)
            : null;
    }

    public function selectUnit(int $unitId): void
    {
        $this->unitId = $unitId;
        $this->search = '';
        unset($this->unit);

        // Si la prenda está disponible, lo usual es que pase a mantenimiento; si ya está ahí, se mantiene.
        $this->after_status = $this->unit?->status === 'available' ? 'maintenance' : 'keep';
    }

    public function clearUnit(): void
    {
        $this->unitId = null;
        $this->after_status = 'keep';
    }

    public function save(MaintenanceService $service): void
    {
        $this->authorize('mantenimiento.registrar');

        $validated = $this->validate([
            'unitId' => ['required', 'integer', 'exists:physical_products,id'],
            'type' => ['required', Rule::in(array_keys(ItemMaintenance::TYPES))],
            'cost' => ['required', 'numeric', 'min:0'],
            'provider_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'maintenance_date' => ['required', 'date', 'before_or_equal:today'],
            'after_status' => ['required', Rule::in(['keep', 'available', 'maintenance'])],
        ], [
            'unitId.required' => 'Busca y elige la prenda por su código.',
        ], [
            'cost' => 'costo',
            'maintenance_date' => 'fecha',
        ]);

        $service->register(PhysicalProduct::findOrFail($validated['unitId']), $validated);

        $this->isOpen = false;
        $this->dispatch('maintenanceSaved');
        Flux::toast(variant: 'success', text: 'Mantenimiento registrado.');
    }

    public function render(): View
    {
        return view('livewire.maintenance.maintenance-form-modal', ['types' => ItemMaintenance::TYPES]);
    }
}
