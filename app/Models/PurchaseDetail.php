<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class PurchaseDetail extends Model
{
    use BelongsToAccount, HasFactory;

    protected $fillable = [
        'purchase_id',
        'product_variant_id',
        'quantity',
        'unit_price',
        'discount_percent',
        'subtotal',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function physicalProducts(): HasMany
    {
        return $this->hasMany(PhysicalProduct::class);
    }

    public function movements(): MorphMany
    {
        return $this->morphMany(InventoryMovement::class, 'reference');
    }
}
