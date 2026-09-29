<?php

namespace App\Livewire\Rental;

use App\Models\PhysicalProduct;
use App\Models\Rental;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class RentalIndex extends Component
{
    use WithPagination;

    #[Url(history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $status = 'all'; // all, out, overdue, returned, cancelled

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    #[On('rentalUpdated')]
    #[On('reservationsChanged')]
    public function refreshList(): void
    {
        //
    }

    public function openRental(int $id): void
    {
        $this->dispatch('open-rental-detail', id: $id);
    }

    public function render(): View
    {
        $rentals = Rental::query()
            ->with(['client:id,display_name,document_number', 'user:id,name'])
            ->withCount('details')
            ->when($this->status === 'overdue', fn ($q) => $q->where('status', Rental::STATUS_OUT)
                ->where('expected_return_date', '<', now()))
            ->when(in_array($this->status, [Rental::STATUS_OUT, Rental::STATUS_RETURNED, Rental::STATUS_CANCELLED]),
                fn ($q) => $q->where('status', $this->status))
            ->when(filled($this->search), function ($q) {
                $term = '%'.trim($this->search).'%';
                $q->where(function ($q) use ($term) {
                    $q->where('code', 'like', $term)
                        ->orWhereHas('client', fn ($c) => $c->where('display_name', 'like', $term)
                            ->orWhere('document_number', 'like', $term));
                });
            })
            ->latest('pickup_date')
            ->latest('id')
            ->paginate(15);

        return view('livewire.rental.rental-index', [
            'rentals' => $rentals,
            'overdueCount' => Rental::where('status', Rental::STATUS_OUT)->where('expected_return_date', '<', now())->count(),
            'reservedCount' => PhysicalProduct::reserved()->count(),
        ]);
    }
}
