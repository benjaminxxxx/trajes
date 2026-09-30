<?php

namespace App\Livewire\Core;

use App\Models\AccountMember;
use App\Models\User;
use App\Services\Core\AccountService;
use App\Services\Core\CurrentAccount;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Agregar personas al negocio y administrar su rol.
 *
 * El usuario (nombre, correo, contraseña) es de la persona y puede trabajar en
 * otros negocios: aquí no se edita ni se borra. Solo se invita, se cambia el
 * rol en este negocio o se le retira el acceso.
 */
class UserFormModal extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $role = 'vendedor';

    public bool $isOwner = false;

    public bool $isActiveMember = true;

    public int $rentalsCount = 0;

    public int $salesCount = 0;

    public bool $isUserFormOpen = false;

    #[On('open-user-modal')]
    public function loadUser(?int $id = null): void
    {
        $this->authorizeAdmin();
        $this->resetValidation();
        $this->reset();

        if ($id) {
            $user = $this->findMember($id);
            $account = $this->account();

            $this->editingId = $user->id;
            $this->name = $user->name;
            $this->email = $user->email;
            $this->role = $user->roles->first()?->name ?? 'vendedor';
            $this->isOwner = $account->isOwnedBy($user);
            $this->isActiveMember = app(AccountService::class)->isActiveMember($account, $user);
            $this->rentalsCount = $user->rentals_count;
            $this->salesCount = $user->sales_count;
        }

        $this->isUserFormOpen = true;
    }

    public function save(AccountService $service): void
    {
        $this->authorizeAdmin();

        $validated = $this->validate([
            'name' => [$this->editingId ? 'nullable' : 'required', 'string', 'max:255'],
            'email' => [$this->editingId ? 'nullable' : 'required', 'email', 'max:255'],
            'role' => ['required', Rule::in(array_keys(AccountService::ROLES))],
        ]);

        $account = $this->account();

        try {
            if ($this->editingId) {
                $service->changeRole($account, $this->findMember($this->editingId), $validated['role'], auth()->user());
            } else {
                $existing = User::withTrashed()->where('email', Str::lower(trim($validated['email'])))->first();

                if ($existing && $service->isActiveMember($account, $existing)) {
                    $this->addError('email', 'Esa persona ya es parte de este negocio.');

                    return;
                }

                $service->inviteByEmail($account, $validated['name'], $validated['email'], $validated['role']);
            }
        } catch (\DomainException $exception) {
            $this->addError('role', $exception->getMessage());

            return;
        }

        $this->isUserFormOpen = false;
        $this->dispatch('userSaved');
        Flux::toast(variant: 'success', text: $this->editingId
            ? 'Rol actualizado.'
            : 'Listo: la persona ya puede entrar a este negocio. Si es nueva, le llegó un correo para crear su contraseña.');
    }

    /**
     * Le quita el acceso a este negocio. Su historial (ventas, alquileres) se conserva.
     */
    public function revoke(AccountService $service): void
    {
        $this->authorizeAdmin();

        try {
            $service->revoke($this->account(), $this->findMember($this->editingId), auth()->user());
        } catch (\DomainException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        $this->isUserFormOpen = false;
        $this->dispatch('userSaved');
        Flux::toast(variant: 'success', text: 'Se retiró el acceso a este negocio.');
    }

    public function transferOwnership(AccountService $service): void
    {
        $this->authorizeAdmin();

        try {
            $service->transferOwnership($this->account(), $this->findMember($this->editingId), auth()->user());
        } catch (\DomainException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        $this->isUserFormOpen = false;
        $this->dispatch('userSaved');
        Flux::toast(variant: 'success', text: "{$this->name} ahora es el dueño del negocio.");
    }

    public function isEditingSelf(): bool
    {
        return $this->editingId !== null && $this->editingId === auth()->id();
    }

    public function canTransferOwnership(): bool
    {
        return $this->editingId
            && ! $this->isOwner
            && $this->isActiveMember
            && $this->role === 'admin'
            && $this->account()->isOwnedBy(auth()->user());
    }

    /**
     * Solo personas que son o fueron miembros de este negocio.
     */
    protected function findMember(?int $id): User
    {
        return User::withTrashed()
            ->whereIn('id', AccountMember::where('account_id', $this->account()->id)->select('user_id'))
            ->with('roles')
            ->withCount(['rentals', 'sales'])
            ->findOrFail($id);
    }

    protected function account()
    {
        return app(CurrentAccount::class)->get() ?? abort(403);
    }

    protected function authorizeAdmin(): void
    {
        $this->authorize('seguridad.usuarios');
    }

    public function render(): View
    {
        return view('livewire.core.user-form-modal');
    }
}
