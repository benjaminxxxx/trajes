<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleDetail extends Model
{
    use BelongsToAccount, HasFactory;

    protected $fillable = [
        'sale_id',
        'product_variant_id',
        'physical_product_id',
        'quantity',
        'unit_cost',
        'unit_price',
        'subtotal',
        'line_total',
    ];

    protected $casts = [
        'unit_cost' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        // Con IGV, tal como se cobró. null en ventas anteriores a su creación.
        'line_total' => 'decimal:2',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function physicalProduct(): BelongsTo
    {
        return $this->belongsTo(PhysicalProduct::class);
    }
}
