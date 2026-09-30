<?php

namespace App\Models;

use App\Services\Core\CurrentAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Para todo modelo del negocio (`use BelongsToAccount;` + columna account_id):
 *
 * - Filtra cada consulta por el negocio actual (CurrentAccount).
 * - Si hay alguien autenticado sin negocio válido, no devuelve nada.
 * - Al crear, asigna el negocio actual; sin negocio, falla (nunca queda huérfano).
 * - Impide mover un registro a otro negocio.
 *
 * Ojo: DB::table() no pasa por aquí; esas consultas deben filtrar con
 * CurrentAccount::requiredId(). AccountIsolationTest verifica que ningún
 * modelo ni tabla del negocio se quede sin esto.
 */
trait BelongsToAccount
{
    public const ACCOUNT_SCOPE = 'account';

    protected static function bootBelongsToAccount(): void
    {
        static::addGlobalScope(self::ACCOUNT_SCOPE, function (Builder $query) {
            $currentAccount = app(CurrentAccount::class);

            if ($currentAccount->isBypassed()) {
                return;
            }

            $accountId = $currentAccount->id();

            if ($accountId !== null) {
                $query->where($query->qualifyColumn('account_id'), $accountId);
            } elseif (Auth::hasUser()) {
                $query->whereRaw('1 = 0');
            }
        });

        static::creating(function (self $model) {
            if ($model->account_id === null) {
                $model->account_id = app(CurrentAccount::class)->id()
                    ?? throw new \LogicException('No hay un negocio activo: no se puede crear '.class_basename($model).'.');
            }
        });

        static::updating(function (self $model) {
            if ($model->isDirty('account_id') && $model->getOriginal('account_id') !== null) {
                throw new \LogicException('Un registro no puede pasar a otro negocio.');
            }
        });
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Consulta sin el filtro por negocio (solo para la plataforma).
     */
    public static function withoutAccountScope(): Builder
    {
        return static::withoutGlobalScope(self::ACCOUNT_SCOPE);
    }
}
