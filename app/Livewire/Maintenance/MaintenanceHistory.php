<?php

namespace App\Livewire\Maintenance;

use App\Livewire\Core\Dashboard;
use App\Models\ItemMaintenance;
use App\Models\Rental;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Mantenimientos de prendas (lavado, arreglos, planchado) con el detalle de
 * cada gasto y el resumen del mes frente a lo cobrado por daños.
 */
#[Title('Mantenimientos')]
class MaintenanceHistory extends Component
{
    use WithPagination;

    #[Url(as: 'mes', history: true)]
    public string $month = '';

    #[Url(history: true)]
    public string $type = 'all';

    #[Url(history: true)]
    public string $search = '';

    public function mount(): void
    {
        $this->month = $this->monthStart()->format('Y-m');

        // Llegar desde la ficha de una prenda abre el registro con esa prenda elegida.
        if (($unitId = request()->integer('prenda')) && auth()->user()->can('mantenimiento.registrar')) {
            $this->dispatch('open-maintenance-modal', unitId: $unitId);
        }
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['month', 'type', 'search'])) {
            $this->resetPage();
        }
    }

    #[On('maintenanceSaved')]
    public function refreshList(): void
    {
        //
    }

    public function deleteMaintenance(int $id): void
    {
        $this->authorize('mantenimiento.registrar');

        ItemMaintenance::findOrFail($id)->delete();

        Flux::toast(variant: 'success', text: 'Registro de mantenimiento eliminado.');
    }

    protected function monthStart(): CarbonImmutable
    {
        try {
            return $this->month !== '' ? CarbonImmutable::createFromFormat('Y-m', $this->month)->startOfMonth() : now()->toImmutable()->startOfMonth();
        } catch (\Throwable) {
            return now()->toImmutable()->startOfMonth();
        }
    }

    public function render(): View
    {
        $from = $this->monthStart();
        $to = $from->endOfMonth();

        $monthQuery = ItemMaintenance::query()->whereBetween('maintenance_date', [$from->toDateString(), $to->toDateString()]);

        $maintenances = (clone $monthQuery)
            ->with(['physicalProduct.variant.product:id,name', 'physicalProduct.variant.size:id,name', 'physicalProduct.variant.color:id,name', 'user:id,name'])
            ->when(array_key_exists($this->type, ItemMaintenance::TYPES), fn ($q) => $q->where('type', $this->type))
            ->when(filled($this->search), fn ($q) => $q->whereHas('physicalProduct', fn ($p) => $p->withTrashed()
                ->where('code', 'like', '%'.trim($this->search).'%')))
            ->orderByDesc('maintenance_date')
            ->orderByDesc('id')
            ->paginate(15);

        return view('livewire.maintenance.maintenance-history', [
            'maintenances' => $maintenances,
            'types' => ItemMaintenance::TYPES,
            'monthLabel' => Dashboard::MONTH_NAMES[$from->month - 1].' '.$from->year,
            'totals' => [
                'count' => (clone $monthQuery)->count(),
                'cost' => (float) (clone $monthQuery)->sum('cost'),
                'by_type' => (clone $monthQuery)->selectRaw('type, SUM(cost) as total')->groupBy('type')->pluck('total', 'type'),
                // Lo cobrado a clientes por daños en el mismo mes, para comparar con lo gastado.
                'charged' => (float) Rental::where('status', Rental::STATUS_RETURNED)
                    ->whereBetween('actual_return_date', [$from, $to->endOfDay()])
                    ->sum('penalty_total'),
            ],
        ]);
    }
}
