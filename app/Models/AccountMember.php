<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pertenencia de una persona a un negocio. Retirarla (status revoked) le
 * quita el acceso a ese negocio sin tocar su usuario ni sus otros negocios.
 * El rol (admin / vendedor) vive en spatie/permission con account_id.
 */
class AccountMember extends Pivot
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVOKED = 'revoked';

    protected $table = 'account_user';

    public $incrementing = true;

    protected $fillable = [
        'account_id',
        'user_id',
        'status',
        'revoked_at',
        'revoked_by',
    ];

    protected $casts = [
        'revoked_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
