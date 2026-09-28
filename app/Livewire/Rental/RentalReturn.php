<?php

namespace App\Livewire\Rental;

use App\Models\Rental;
use App\Models\RentalDetail;
use App\Services\Rental\RentalService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Recepción de un alquiler: fecha, estado de cada prenda, cobro por daños y
 * observaciones. Si el cliente decide comprar una prenda, primero se cierra
 * el alquiler aquí y luego se vende esa prenda desde Ventas.
 */
#[Title('Recepcionar alquiler')]
class RentalReturn extends Component
{
    public Rental $rental;

    public string $actual_return_date = '';

    public ?string $return_notes = null;

    /** @var array<int, array{condition: string, damage_fee: float|string, damage_note: ?string, next_status: string}> */
    public array $items = [];

    public bool $completed = false;

    public function mount(Rental $rental): void
    {
        $this->authorize('alquileres.recepcionar');

        $this->rental = $rental;
        $this->completed = $rental->status === Rental::STATUS_RETURNED;
        $this->actual_return_date = now()->format('Y-m-d\TH:i');

        foreach ($rental->details as $detail) {
            $this->items[$detail->id] = [
                'condition' => 'good',
                'damage_fee' => 0,
                'damage_note' => null,
                'next_status' => 'available',
            ];
        }
    }

    #[Computed]
    public function details()
    {
        return $this->rental->details()
            ->with(['physicalProduct.variant.product:id,name,code', 'physicalProduct.variant.size:id,name', 'physicalProduct.variant.color:id,name'])
            ->get();
    }

    /**
     * Al elegir la condición se sugiere a dónde va la prenda: dañada a
     * mantenimiento; buena o sucia de vuelta a disponible.
     */
    public function updatedItems(mixed $value, string $key): void
    {
        [$detailId, $field] = array_pad(explode('.', $key), 2, null);

        if ($field === 'condition' && isset($this->items[$detailId])) {
            $this->items[$detailId]['next_status'] = $value === 'damaged' ? 'maintenance' : 'available';
        }
    }

    public function save(RentalService $service): void
    {
        $this->authorize('alquileres.recepcionar');

        $this->validate([
            'actual_return_date' => ['required', 'date'],
            'return_notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array'],
            'items.*.condition' => ['required', Rule::in(array_keys(RentalDetail::CONDITION_LABELS))],
            'items.*.damage_fee' => ['nullable', 'numeric', 'min:0'],
            'items.*.damage_note' => ['nullable', 'string', 'max:500'],
            'items.*.next_status' => ['required', Rule::in(['available', 'maintenance'])],
        ], [], [
            'actual_return_date' => 'fecha de recepción',
            'items.*.damage_fee' => 'cobro por daño',
        ]);

        try {
            $service->receive($this->rental, [
                'actual_return_date' => $this->actual_return_date,
                'return_notes' => $this->return_notes,
                'items' => $this->items,
            ]);
        } catch (\RuntimeException $e) {
            $this->addError('items', $e->getMessage());

            return;
        }

        $this->rental->refresh();
        unset($this->details);
        $this->completed = true;
    }

    public function render(): View
    {
        return view('livewire.rental.rental-return', [
            'conditions' => RentalDetail::CONDITION_LABELS,
        ]);
    }
}
