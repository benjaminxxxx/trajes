<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Desgaste de prendas físicas: desde `min_rentals` alquileres, su valor baja `percent` %.
 */
class DepreciationRule extends Model
{
    use AuditsChanges, BelongsToAccount;

    protected $fillable = [
        'min_rentals',
        'percent',
    ];

    protected $casts = [
        'min_rentals' => 'integer',
        'percent' => 'decimal:2',
    ];
}
