<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Bitácora de eliminaciones. account_id es opcional (las acciones de la
 * plataforma no pertenecen a un negocio), por eso no usa BelongsToAccount:
 * al consultarla desde un negocio, filtrar por account_id.
 */
class AuditLog extends Model
{
    // Solo created_at: una bitácora no se modifica.
    public const UPDATED_AT = null;

    protected $fillable = [
        'account_id',
        'auditable_type',
        'auditable_id',
        'event',
        'user_id',
        'user_name',
        'old_values',
    ];

    protected $casts = [
        'old_values' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Apunta al modelo original; devuelve null porque ya no existe (fue eliminado).
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
