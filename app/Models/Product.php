<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use BelongsToAccount, HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'brand_id',
        'code',
        'name',
        'slug',
        'description',
        'price',
        'gender',
        'allow_sale',
        'allow_rental',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'allow_sale' => 'boolean',
        'allow_rental' => 'boolean',
        'is_active' => 'boolean',
    ];

    // ==========================================
    // RELACIONES PERTENECIENTES (BelongsTo)
    // ==========================================

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    // ==========================================
    // RELACIONES Múltiples (HasMany / BelongsToMany)
    // ==========================================
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Galería completa de imágenes y videos del producto.
     */
    public function media(): HasMany
    {
        return $this->hasMany(ProductMedia::class)->orderBy('sort_order');
    }

    /**
     * Imagen principal del producto.
     */
    public function primaryMedia(): HasOne
    {
        return $this->hasOne(ProductMedia::class)->where('is_primary', true);
    }

    /**
     * Atributos de ficha técnica asociados al producto (Corte, Solapa, Mantenimiento, etc.).
     */
    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class, 'product_attribute_values')
            ->withTimestamps();
    }

    /**
     * Unidades físicas o prendas en inventario (PhysicalItems / InventoryItems).
     */
    public function physicalProducts(): HasManyThrough
    {
        return $this->hasManyThrough(
            PhysicalProduct::class,
            ProductVariant::class,
            'product_id',         // Foreign key en product_variants
            'product_variant_id', // Foreign key en physical_products
            'id',                 // Local key en products
            'id'                  // Local key en product_variants
        );
    }
}
