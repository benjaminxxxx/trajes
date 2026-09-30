<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InventoryMovement extends Model
{
    use BelongsToAccount, HasFactory;

    public const TYPE_IN = 'in';

    public const TYPE_OUT = 'out';

    public const CONCEPT_PURCHASE = 'purchase';

    public const CONCEPT_PURCHASE_CORRECTION = 'purchase_correction';

    public const CONCEPT_SALE = 'sale';

    public const CONCEPT_SALE_CANCELLED = 'sale_cancelled';

    public const CONCEPT_RENTAL = 'rental';

    public const CONCEPT_LOST = 'lost';

    public const CONCEPT_STOLEN = 'stolen';

    public const CONCEPT_ADJUSTMENT_IN = 'adjustment_in';

    public const CONCEPT_ADJUSTMENT_OUT = 'adjustment_out';

    protected $fillable = [
        'product_variant_id',
        'physical_product_id',
        'user_id',
        'type',
        'concept',
        'quantity',
        'unit_cost',
        'total_cost',
        'reference_id',
        'reference_type',
        'notes',
    ];

    protected $casts = [
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function physicalProduct(): BelongsTo
    {
        return $this->belongsTo(PhysicalProduct::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
