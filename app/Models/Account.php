<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un negocio que usa el sistema. Todo lo del negocio (productos, ventas,
 * clientes...) cuelga de aquí por account_id; las personas entran como
 * miembros (account_user). El dueño se cambia solo con AccountService::transferOwnership.
 */
class Account extends Model
{
    public const STATUS_TRIAL = 'trial';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_BLOCKED = 'blocked';

    public const STATUS_LABELS = [
        self::STATUS_TRIAL => 'Prueba',
        self::STATUS_ACTIVE => 'Activa',
        self::STATUS_SUSPENDED => 'Suspendida',
        self::STATUS_BLOCKED => 'Bloqueada',
    ];

    /**
     * owner_id y status no son asignables en masa: solo cambian por
     * AccountService (transferencia de propiedad) o desde la plataforma.
     */
    protected $fillable = [
        'name',
        'ruc',
        'address',
        'phone',
        'receipt_footer',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'blocked_at' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(AccountMember::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(AccountMember::class)
            ->withPivot(['id', 'status', 'revoked_at', 'revoked_by'])
            ->withTimestamps();
    }

    public function isOwnedBy(User|int|null $user): bool
    {
        $userId = $user instanceof User ? $user->id : $user;

        return $userId !== null && (int) $this->owner_id === (int) $userId;
    }

    public function isBlocked(): bool
    {
        return $this->status === self::STATUS_BLOCKED;
    }

    /**
     * Cuentas en las que se puede trabajar (todas menos las bloqueadas).
     */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_BLOCKED);
    }

    /**
     * Datos del negocio para los comprobantes, con los de config/business.php como respaldo.
     *
     * @return array{name: string, ruc: ?string, address: ?string, phone: ?string, receipt_footer: ?string}
     */
    public function receiptData(): array
    {
        return [
            'name' => $this->name,
            'ruc' => $this->ruc,
            'address' => $this->address,
            'phone' => $this->phone,
            'receipt_footer' => $this->receipt_footer ?: config('business.receipt_footer'),
        ];
    }
}
