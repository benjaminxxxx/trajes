<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PhysicalProduct extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'physical_products';

    protected $fillable = [
        'code',
        'product_variant_id',
        'purchase_detail_id',
        'purchase_cost',
        'suggested_sale_price',
        'final_sale_price',
        'rental_price',
        'status',
        'status_note',
        'sold_at',
    ];

    protected $casts = [
        'purchase_cost' => 'decimal:2',
        'suggested_sale_price' => 'decimal:2',
        'final_sale_price' => 'decimal:2',
        'rental_price' => 'decimal:2',
        'sold_at' => 'date',
    ];

    // ==========================================
    // RELACIONES PERTENECIENTES (BelongsTo)
    // ==========================================

    /**
     * Variante del producto a la que pertenece esta prenda física (Talla y Color).
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * Detalle de compra origen de esta unidad física.
     */
    public function purchaseDetail(): BelongsTo
    {
        return $this->belongsTo(PurchaseDetail::class, 'purchase_detail_id');
    }
    public function physicalProducts(): HasMany
    {
        return $this->hasMany(PhysicalProduct::class, 'product_variant_id');
    }
    // ==========================================
    // SCOPES DE ESTADO
    // ==========================================

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    public function scopeRented($query)
    {
        return $query->where('status', 'rented');
    }

    public function scopeInMaintenance($query)
    {
        return $query->where('status', 'maintenance');
    }
}