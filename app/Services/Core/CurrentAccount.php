<?php

namespace App\Services\Core;

use App\Models\Account;
use App\Models\AccountMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * El negocio con el que se está trabajando en este request.
 *
 * - En la web lo fija el middleware EnsureCurrentAccount a partir de
 *   users.current_account_id (validando que la persona siga siendo miembro y
 *   que la cuenta no esté bloqueada). Si nadie lo fijó (ej. un request de
 *   Livewire), se resuelve igual desde el usuario autenticado.
 * - En consola, seeders o jobs se fija con set() o runAs().
 *
 * BelongsToAccount filtra todas las consultas por este id. Si hay una persona
 * autenticada pero ningún negocio válido, el filtro no devuelve nada (nunca todo).
 */
class CurrentAccount
{
    protected ?Account $account = null;

    protected bool $isExplicit = false;

    protected ?string $resolvedFor = null;

    protected bool $isBypassed = false;

    public function set(?Account $account): void
    {
        $this->account = $account;
        $this->isExplicit = $account !== null;
        $this->resolvedFor = null;
        $this->refreshUserRoles();
    }

    public function forget(): void
    {
        $this->set(null);
    }

    public function get(): ?Account
    {
        if ($this->isExplicit) {
            return $this->account;
        }

        $user = Auth::user();

        if (! $user instanceof User) {
            return null;
        }

        $cacheKey = $user->id.':'.$user->current_account_id;

        if ($this->resolvedFor !== $cacheKey) {
            $this->account = $this->resolveFor($user);
            $this->resolvedFor = $cacheKey;
        }

        return $this->account;
    }

    public function id(): ?int
    {
        return $this->get()?->id;
    }

    /**
     * Id obligatorio: para consultas que no pasan por Eloquent (DB::table).
     */
    public function requiredId(): int
    {
        return $this->id() ?? throw new \LogicException('No hay un negocio activo para esta operación.');
    }

    /**
     * Ejecuta algo dentro de otro negocio y vuelve al anterior.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function runAs(Account $account, callable $callback): mixed
    {
        [$previous, $wasExplicit, $resolvedFor] = [$this->account, $this->isExplicit, $this->resolvedFor];

        $this->set($account);

        try {
            return $callback();
        } finally {
            [$this->account, $this->isExplicit, $this->resolvedFor] = [$previous, $wasExplicit, $resolvedFor];
            $this->refreshUserRoles();
        }
    }

    /**
     * Ejecuta algo sin el filtro por negocio (panel de la plataforma, reportes globales).
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function withoutScope(callable $callback): mixed
    {
        $previous = $this->isBypassed;
        $this->isBypassed = true;

        try {
            return $callback();
        } finally {
            $this->isBypassed = $previous;
        }
    }

    public function isBypassed(): bool
    {
        return $this->isBypassed;
    }

    /**
     * Negocios en los que la persona puede trabajar ahora.
     *
     * @return Collection<int, Account>
     */
    public function usableAccountsFor(User $user)
    {
        return Account::usable()
            ->whereIn('id', AccountMember::active()->where('user_id', $user->id)->select('account_id'))
            ->orderBy('name')
            ->get();
    }

    /**
     * El negocio elegido por la persona si sigue siendo válido; si no, el
     * primero en el que pueda trabajar (y queda como su negocio actual).
     */
    protected function resolveFor(User $user): ?Account
    {
        $accounts = $this->usableAccountsFor($user);
        $account = $accounts->firstWhere('id', $user->current_account_id) ?? $accounts->first();

        if ($account && (int) $user->current_account_id !== $account->id) {
            $user->forceFill(['current_account_id' => $account->id])->saveQuietly();
        }

        return $account;
    }

    /**
     * Los roles de spatie dependen del negocio: al cambiarlo se recargan.
     */
    protected function refreshUserRoles(): void
    {
        $user = Auth::user();

        if ($user instanceof User) {
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
    }
}
