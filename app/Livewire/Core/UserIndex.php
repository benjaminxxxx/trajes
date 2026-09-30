<?php

namespace App\Livewire\Core;

use App\Models\AccountMember;
use App\Models\User;
use App\Services\Core\AccountService;
use App\Services\Core\CurrentAccount;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Personas con acceso al negocio actual (y las que fueron retiradas).
 */
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

    /**
     * Devuelve el acceso a alguien retirado (como vendedor).
     */
    public function restoreUser(int $id, AccountService $service): void
    {
        $this->authorize('seguridad.usuarios');

        $account = app(CurrentAccount::class)->get();
        $member = AccountMember::where('account_id', $account->id)->where('user_id', $id)->firstOrFail();
        $user = User::withTrashed()->findOrFail($member->user_id);

        if ($user->trashed()) {
            $user->restore();
        }

        $service->restore($account, $user);

        Flux::toast(variant: 'success', text: "{$user->name} vuelve a tener acceso como vendedor.");
    }

    public function render(): View
    {
        $account = app(CurrentAccount::class)->get();

        $query = User::withTrashed()->whereIn('id', AccountMember::query()
            ->where('account_id', $account?->id)
            ->where('status', $this->showArchived ? AccountMember::STATUS_REVOKED : AccountMember::STATUS_ACTIVE)
            ->select('user_id'));

        if (in_array($this->role, ['admin', 'vendedor'])) {
            $query->whereHas('roles', fn ($roles) => $roles->where('name', $this->role));
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
            'ownerId' => $account?->owner_id,
        ]);
    }
}
