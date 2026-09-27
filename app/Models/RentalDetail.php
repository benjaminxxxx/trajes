<?php

namespace App\Models;

use App\Models\PhysicalProduct;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'rental_id',
        'physical_product_id',
        'price',
        'return_condition',
        'damage_fee',
        'damage_note',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'damage_fee' => 'decimal:2',
    ];

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function physicalProduct(): BelongsTo
    {
        return $this->belongsTo(PhysicalProduct::class);
    }
}