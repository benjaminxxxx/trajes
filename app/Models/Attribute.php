<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Característica que se pide al registrar un producto (Tela, Corte, Cuero).
 * Sus valores son solo SUGERENCIAS: el producto guarda el texto elegido
 * (ProductCharacteristic), así que renombrar o borrar una sugerencia no
 * cambia nada ya registrado.
 */
class Attribute extends Model
{
    use AuditsChanges, BelongsToAccount, HasFactory;

    protected $fillable = [
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class)->orderBy('value');
    }

    /**
     * Categorías donde se pide; sin ninguna, se pide en todas.
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->withTimestamps();
    }

    public function productCharacteristics(): HasMany
    {
        return $this->hasMany(ProductCharacteristic::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Activas que aplican a la categoría (las vinculadas a ella y las generales).
     */
    public function scopeForCategory(Builder $query, ?int $categoryId): Builder
    {
        return $query->active()->where(function (Builder $query) use ($categoryId) {
            $query->whereDoesntHave('categories')
                ->when($categoryId, fn (Builder $q) => $q->orWhereHas('categories', fn (Builder $c) => $c->whereKey($categoryId)));
        });
    }
}
