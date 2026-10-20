<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Etiqueta escrita a mano en un producto (Quinceañera, Señora, Boda...).
 * Las automáticas (categoría, género, talla, color, características) no se
 * guardan aquí: se leen de su origen, así nunca quedan desactualizadas.
 */
class ProductTag extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'product_id',
        'name',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
