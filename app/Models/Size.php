<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Size extends Model
{
    use AuditsChanges, BelongsToAccount, HasFactory;

    /**
     * Grupos de talla sugeridos (el campo `type` acepta cualquier texto).
     */
    public const TYPES = [
        'estandar' => 'Estándar (S, M, L)',
        'terno' => 'Terno (numérica)',
        'nino' => 'Niño / Niña',
        'calzado_hombre' => 'Calzado de varón',
        'calzado_mujer' => 'Calzado de dama',
        'calzado_nino' => 'Calzado infantil',
    ];

    protected $fillable = [
        'name',
        'type',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Si algún producto usa la talla, su nombre ya no se puede cambiar
     * (renombrar "M" a "XL" cambiaría también las prendas en stock y el historial).
     */
    public function isInUse(): bool
    {
        return $this->variants()->exists();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
