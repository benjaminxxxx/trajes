<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gasto de mantenimiento de una prenda física (lavandería, arreglo de sastre,
 * planchado...). Se registra por prenda para sumarlo a su historial de
 * ganancias y pérdidas, a diferencia de un gasto operativo general.
 */
class ItemMaintenance extends Model
{
    use BelongsToAccount;

    protected $table = 'item_maintenances';

    public const TYPES = [
        'laundromat' => 'Lavandería',
        'tailor_repair' => 'Arreglo de sastre',
        'ironing' => 'Planchado',
        'other' => 'Otro',
    ];

    protected $fillable = [
        'physical_product_id',
        'user_id',
        'rental_id',
        'type',
        'cost',
        'provider_name',
        'description',
        'maintenance_date',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'maintenance_date' => 'date',
    ];

    public function physicalProduct(): BelongsTo
    {
        return $this->belongsTo(PhysicalProduct::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class)->withTrashed();
    }
}
