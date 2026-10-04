<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bien del negocio que salió con una venta: regalado (line_total = 0, ej. el
 * portaternos) o vendido (line_total = lo cobrado con IGV).
 */
class SaleAssetDelivery extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'sale_id',
        'asset_id',
        'quantity',
        'unit_cost',
        'line_total',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:4',
        'line_total' => 'decimal:2',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withTrashed();
    }

    /**
     * Lo cobrado por la línea sin IGV (0 si fue regalo), comparable con el costo.
     */
    public function netTotal(): float
    {
        return round((float) $this->line_total / 1.18, 2);
    }

    public function totalCost(): float
    {
        return round($this->quantity * (float) $this->unit_cost, 2);
    }
}
