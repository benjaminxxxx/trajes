<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Bien del negocio que no se vende: mobiliario, maniquíes, ganchos, o
 * consumibles como portaternos y correas. Suma al valorizado de la empresa.
 * Se elimina de forma auditada (quién y cuándo, en audit_logs).
 */
class Asset extends Model
{
    use AuditsDeletion, BelongsToAccount, SoftDeletes;

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
        'is_consumable',
        'delivers_with_sale',
        'status',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:4',
        'current_unit_value' => 'decimal:4',
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
     * Consumibles activos que el formulario de venta ofrece entregar.
     */
    public function scopeDeliverable(Builder $query): Builder
    {
        return $query->active()->where('is_consumable', true)->where('delivers_with_sale', true);
    }
}
