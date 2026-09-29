<?php

namespace App\Livewire\Rental;

use App\Models\PhysicalProduct;
use App\Services\Rental\ReservationService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Lista de prendas separadas (reservas informativas) con la opción de
 * liberarlas. Las vencidas se muestran primero para limpiarlas.
 */
class ReservationsModal extends Component
{
    public bool $isOpen = false;

    #[On('open-reservations')]
    public function open(): void
    {
        unset($this->reservations);
        $this->isOpen = true;
    }

    #[Computed]
    public function reservations(): Collection
    {
        return PhysicalProduct::reserved()
            ->with([
                'reservedClient:id,display_name,mobile',
                'variant.product:id,name',
                'variant.size:id,name',
                'variant.color:id,name',
            ])
            ->orderBy('reserved_until')
            ->get();
    }

    public function release(int $unitId, ReservationService $reservations): void
    {
        $this->authorize('alquileres.registrar');

        $unit = PhysicalProduct::reserved()->findOrFail($unitId);
        $reservations->release($unit);

        unset($this->reservations);
        $this->dispatch('reservationsChanged');
        Flux::toast(variant: 'success', text: "Se liberó la prenda {$unit->code}.");
    }

    public function render(): View
    {
        return view('livewire.rental.reservations-modal');
    }
}
