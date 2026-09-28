<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class OperationalExpense extends Model
{
    use AuditsDeletion, HasFactory;

    protected $fillable = [
        'user_id',
        'category',
        'concept',
        'amount',
        'expense_date',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'datetime',
    ];

    protected static function booted(): void
    {
        // La auditoría solo registra eliminaciones: si se pudiera editar, un
        // cambio quedaría sin rastro. Para corregir: eliminar y volver a crear.
        static::updating(function () {
            throw new \LogicException('Los gastos operativos no se pueden editar: elimínalo y vuelve a registrarlo.');
        });
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
