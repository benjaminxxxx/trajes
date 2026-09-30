<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Consumible (portaternos, correa...) entregado con una venta.
 */
class SaleAssetDelivery extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'sale_id',
        'asset_id',
        'quantity',
        'unit_cost',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:4',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withTrashed();
    }

    public function totalCost(): float
    {
        return round($this->quantity * (float) $this->unit_cost, 2);
    }
}
