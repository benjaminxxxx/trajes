<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalMedia extends Model
{
    use HasFactory;

    protected $table = 'rental_media';

    protected $fillable = [
        'rental_id',
        'file_path',
        'type', // 'dni', 'prenda_entrega', 'prenda_devolucion', 'voucher'
    ];

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }
}