<?php

namespace App\Http\Middleware;

use App\Models\Account;
use App\Models\AccountMember;
use App\Services\Core\CurrentAccount;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fija el negocio del request a partir de la persona autenticada.
 *
 * - Sin negocio elegido o con acceso retirado → pantalla para elegir o crear uno.
 * - Si todos sus negocios están bloqueados → se cierra la sesión: nadie, ni
 *   los vendedores, entra a un negocio bloqueado.
 *
 * Se re-aplica en cada request de Livewire (persistent middleware).
 */
class EnsureCurrentAccount
{
    public function __construct(protected CurrentAccount $currentAccount) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Se resuelve siempre desde la persona, nunca desde un valor anterior.
        $this->currentAccount->forget();
        $account = $this->currentAccount->get();

        if ($account) {
            $this->currentAccount->set($account);

            return $next($request);
        }

        // El administrador de la plataforma no necesita un negocio propio.
        if ($user->is_platform_admin) {
            return redirect()->route('platform.accounts.index');
        }

        if ($this->onlyHasBlockedAccounts($user->id)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('status', 'El acceso a tu negocio está bloqueado. Comunícate con Kipusoft.');
        }

        return redirect()->route('accounts.select');
    }

    protected function onlyHasBlockedAccounts(int $userId): bool
    {
        return Account::where('status', Account::STATUS_BLOCKED)
            ->whereIn('id', AccountMember::active()->where('user_id', $userId)->select('account_id'))
            ->exists();
    }
}
