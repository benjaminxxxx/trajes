<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Color extends Model
{
    use BelongsToAccount, HasFactory;

    protected $fillable = [
        'name',
        'hex_code',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->withTimestamps();
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Si algún producto usa el color, su nombre ya no se puede cambiar
     * (el código hex sí, porque solo es visual).
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
