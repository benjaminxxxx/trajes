<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Característica de un producto guardada como texto (Tela: Satín). El
 * catálogo de características solo sugiere: cambiarlo no toca esta fila.
 */
class ProductCharacteristic extends Model
{
    use AuditsChanges, BelongsToAccount;

    protected $fillable = [
        'product_id',
        'attribute_id',
        'name',
        'value',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }
}
