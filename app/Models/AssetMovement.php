<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Historial de un bien: ingresos, bajas, entregas con ventas y cambios de valor.
 */
class AssetMovement extends Model
{
    use BelongsToAccount;

    public const UPDATED_AT = null;

    public const TYPES = [
        'initial' => 'Registro inicial',
        'in' => 'Ingreso',
        'discharge' => 'Baja',
        'delivery' => 'Entregado con venta',
        'sale' => 'Vendido',
        'delivery_return' => 'Devuelto por anulación',
        'revalue' => 'Cambio de valor',
    ];

    protected $fillable = [
        'asset_id',
        'user_id',
        'type',
        'quantity',
        'unit_cost',
        'reference_id',
        'reference_type',
        'note',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:4',
        'created_at' => 'datetime',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
