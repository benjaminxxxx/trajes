<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Bien del negocio: mobiliario, maniquíes, ganchos, o consumibles como
 * portaternos y correas. Suma al valorizado de la empresa. Con `sale_price`
 * también se puede vender desde el panel de ventas.
 * Se elimina de forma auditada (quién y cuándo, en audit_logs).
 */
class Asset extends Model
{
    use AuditsChanges, BelongsToAccount, SoftDeletes;

    public const TYPES = [
        'mobiliario' => 'Mobiliario',
        'exhibicion' => 'Exhibición',
        'empaque' => 'Empaque',
        'accesorio' => 'Accesorio',
        'herramienta' => 'Herramienta / equipo',
        'otro' => 'Otro',
    ];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DISCHARGED = 'discharged';

    protected $fillable = [
        'name',
        'type',
        'quantity',
        'unit_cost',
        'current_unit_value',
        'sale_price',
        'is_consumable',
        'delivers_with_sale',
        'status',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:4',
        'current_unit_value' => 'decimal:4',
        'sale_price' => 'decimal:2',
        'is_consumable' => 'boolean',
        'delivers_with_sale' => 'boolean',
    ];

    /**
     * Categorías de producto con las que se entrega (vacío = todas).
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(AssetMovement::class)->latest('id');
    }

    public function totalCost(): float
    {
        return round($this->quantity * (float) $this->unit_cost, 2);
    }

    public function totalValue(): float
    {
        return round($this->quantity * (float) $this->current_unit_value, 2);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Bienes con precio de venta: se pueden vender desde el panel de ventas.
     */
    public function scopeSellable(Builder $query): Builder
    {
        return $query->active()->whereNotNull('sale_price');
    }

    /**
     * La cantidad y el estado ya dejan su rastro en asset_movements.
     */
    public function auditExcluded(): array
    {
        return ['quantity', 'status'];
    }

    /**
     * Consumibles activos que el formulario de venta ofrece entregar.
     */
    public function scopeDeliverable(Builder $query): Builder
    {
        return $query->active()->where('is_consumable', true)->where('delivers_with_sale', true);
    }
}
