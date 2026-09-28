<?php

namespace App\Livewire\Core;

use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\On;
use Livewire\Component;

class UserFormModal extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $role = 'vendedor';

    public bool $is_active = true;

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
            $user = User::with('roles')->withCount(['rentals', 'sales'])->findOrFail($id);

            $this->editingId = $user->id;
            $this->name = $user->name;
            $this->email = $user->email;
            $this->role = $user->roles->first()?->name ?? 'vendedor';
            $this->is_active = (bool) $user->is_active;
            $this->rentalsCount = $user->rentals_count;
            $this->salesCount = $user->sales_count;
        }

        $this->isUserFormOpen = true;
    }

    public function save(): void
    {
        $this->authorizeAdmin();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)],
            'password' => [$this->editingId ? 'nullable' : 'required', 'string', Password::defaults()],
            'role' => ['required', Rule::in(['admin', 'vendedor'])],
            'is_active' => ['boolean'],
        ]);

        if ($this->isEditingSelf() && ($validated['role'] !== 'admin' || ! $validated['is_active'])) {
            $this->addError('role', 'No puedes quitarte el rol de administrador ni desactivarte a ti mismo.');

            return;
        }

        $user = $this->editingId ? User::findOrFail($this->editingId) : new User;

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'is_active' => $validated['is_active'],
        ]);

        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        if (! $user->exists) {
            $user->email_verified_at = now();
        }

        $user->save();
        $user->syncRoles([$validated['role']]);

        $this->isUserFormOpen = false;
        $this->dispatch('userSaved');
        Flux::toast(variant: 'success', text: 'Usuario guardado correctamente.');
    }

    /**
     * Users with operational history are soft deleted to preserve traceability;
     * users without any history are removed permanently.
     */
    public function deleteUser(): void
    {
        $this->authorizeAdmin();

        if (! $this->editingId) {
            return;
        }

        if ($this->isEditingSelf()) {
            Flux::toast(variant: 'danger', text: 'No puedes eliminar tu propio usuario.');

            return;
        }

        $user = User::findOrFail($this->editingId);

        if ($user->hasActivity()) {
            $user->delete();
            Flux::toast(variant: 'success', text: 'El usuario tiene movimientos registrados, fue dado de baja.');
        } else {
            $user->syncRoles([]);
            $user->forceDelete();
            Flux::toast(variant: 'success', text: 'Usuario eliminado permanentemente.');
        }

        $this->isUserFormOpen = false;
        $this->dispatch('userSaved');
    }

    public function isEditingSelf(): bool
    {
        return $this->editingId !== null && $this->editingId === auth()->id();
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
