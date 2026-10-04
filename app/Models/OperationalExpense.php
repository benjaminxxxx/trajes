<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class OperationalExpense extends Model
{
    use AuditsChanges, BelongsToAccount, HasFactory;

    protected $fillable = [
        'user_id',
        'category',
        'concept',
        'amount',
        'expense_date',
        'notes',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public const CANCELLATION_FIELDS = ['cancelled_at', 'cancelled_by', 'cancellation_reason'];

    protected static function booted(): void
    {
        // Un gasto ya movió la caja: no se edita ni se elimina. Lo único que
        // puede cambiar es su anulación (ExpenseService::cancel), que devuelve
        // el dinero a caja con un reingreso.
        static::updating(function (self $expense) {
            if (array_diff(array_keys($expense->getDirty()), [...self::CANCELLATION_FIELDS, 'updated_at']) !== []) {
                throw new \LogicException('Los gastos operativos no se pueden editar: anúlalo y vuelve a registrarlo.');
            }
        });

        static::deleting(function () {
            throw new \LogicException('Los gastos operativos no se eliminan: anúlalo para que la caja conserve su historia.');
        });
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /**
     * Gastos vigentes: los anulados no cuentan en totales ni reportes.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('cancelled_at');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cashMovements(): MorphMany
    {
        return $this->morphMany(CashMovement::class, 'reference');
    }
}
