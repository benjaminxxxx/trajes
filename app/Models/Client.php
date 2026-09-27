<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'type',
        'document_type',
        'document_number',
        'names',
        'last_name',
        'birth_date',
        'gender',
        'company_name',
        'display_name',
        'mobile',
        'phone',
        'email',
        'address',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'birth_date' => 'date',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    /**
     * Ventas asociadas al cliente.
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * Alquileres asociados al cliente.
     */
    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }
    /**
     * Retorna un color consistente para el avatar según el ID.
     */
    public function getAvatarColorAttribute(): string
    {
        $colors = [
            'violet',
            'indigo',
            'blue',
            'sky',
            'teal',
            'emerald',
            'green',
            'amber',
            'rose',
            'fuchsia'
        ];

        return $colors[$this->id % count($colors)];
    }
    /**
     * Facturas / Comprobantes asociados al cliente.
     */
    /*
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }*/

    /*
    |--------------------------------------------------------------------------
    | Accessors & Mutators
    |--------------------------------------------------------------------------
    */

    /**
     * Obtiene la denominación comercial o el nombre completo.
     * Si no tiene display_name guardado, retorna la razón social o nombres y apellidos.
     */
    public function getDisplayNameAttribute($value): string
    {
        if (!empty($value)) {
            return $value;
        }

        if ($this->type === 'PJ' || !empty($this->company_name)) {
            return $this->company_name ?? '';
        }

        return trim("{$this->names} {$this->last_name}");
    }
}