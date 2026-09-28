<?php

namespace App\Livewire\Core;

use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class UserIndex extends Component
{
    use WithPagination;

    #[Url(history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $role = 'all'; // all, admin, vendedor

    #[Url(history: true)]
    public bool $showArchived = false;

    public function mount(): void
    {
        $this->authorize('seguridad.usuarios');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingRole(): void
    {
        $this->resetPage();
    }

    public function updatingShowArchived(): void
    {
        $this->resetPage();
    }

    #[On('userSaved')]
    public function refreshList(): void
    {
        //
    }

    public function openCreateModal(): void
    {
        $this->dispatch('open-user-modal');
    }

    public function openEditModal(int $id): void
    {
        $this->dispatch('open-user-modal', id: $id);
    }

    public function restoreUser(int $id): void
    {
        $this->authorize('seguridad.usuarios');

        User::onlyTrashed()->findOrFail($id)->restore();

        Flux::toast(variant: 'success', text: 'Usuario restaurado correctamente.');
    }

    public function render(): View
    {
        $query = $this->showArchived
            ? User::onlyTrashed()
            : User::query();

        if (in_array($this->role, ['admin', 'vendedor'])) {
            $query->role($this->role);
        }

        if (! empty($this->search)) {
            $searchTerm = '%'.trim($this->search).'%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', $searchTerm)
                    ->orWhere('email', 'like', $searchTerm);
            });
        }

        $users = $query->with('roles')
            ->withCount(['rentals', 'sales'])
            ->latest()
            ->paginate(8);

        return view('livewire.core.user-index', [
            'users' => $users,
        ]);
    }
}
