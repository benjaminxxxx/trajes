<?php

namespace App\Livewire\Core;

use App\Models\Account;
use App\Models\AccountMember;
use App\Services\Core\AccountService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Elegir el negocio con el que trabajar o registrar uno nuevo. Aquí llega
 * quien recién se registró, quien trabaja en varios negocios, o quien fue
 * retirado del negocio que usaba.
 */
#[Title('Mis negocios')]
#[Layout('layouts::auth')]
class AccountPicker extends Component
{
    public string $name = '';

    public ?string $ruc = null;

    public bool $isCreating = false;

    public function mount(): void
    {
        $hasMemberships = $this->memberships()->isNotEmpty();

        // El administrador de la plataforma no necesita un negocio propio.
        if (! $hasMemberships && Auth::user()->is_platform_admin) {
            $this->redirectRoute('platform.accounts.index');

            return;
        }

        $this->isCreating = ! $hasMemberships;
    }

    public function enter(int $accountId): void
    {
        $user = Auth::user();

        $isAllowed = Account::usable()->whereKey($accountId)
            ->whereIn('id', AccountMember::active()->where('user_id', $user->id)->select('account_id'))
            ->exists();

        if (! $isAllowed) {
            $this->addError('account', 'No tienes acceso a ese negocio.');

            return;
        }

        $user->forceFill(['current_account_id' => $accountId])->save();

        $this->redirectRoute('dashboard', navigate: false);
    }

    public function create(AccountService $service): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'ruc' => ['nullable', 'digits:11'],
        ], [], ['name' => 'nombre del negocio', 'ruc' => 'RUC']);

        $service->createAccount(Auth::user(), ['name' => trim($this->name), 'ruc' => $this->ruc ?: null]);

        $this->redirectRoute('dashboard', navigate: false);
    }

    /**
     * @return Collection<int, AccountMember>
     */
    protected function memberships()
    {
        return AccountMember::active()
            ->where('user_id', Auth::id())
            ->with('account')
            ->get()
            ->filter(fn (AccountMember $membership) => $membership->account !== null)
            ->sortBy(fn (AccountMember $membership) => $membership->account->name)
            ->values();
    }

    public function render(): View
    {
        return view('livewire.core.account-picker', [
            'memberships' => $this->memberships(),
            'currentAccountId' => Auth::user()->current_account_id,
        ]);
    }
}
