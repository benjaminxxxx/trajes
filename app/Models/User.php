<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'google_id'])]
/**
 * Debe verificar su correo quien se registra con el formulario. Quien entra
 * con Google o es invitado por un negocio ya queda verificado.
 */
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, SoftDeletes, TwoFactorAuthenticatable;

    /**
     * current_account_id e is_platform_admin no son asignables en masa.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'person_id',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_platform_admin' => 'boolean',
        ];
    }

    /**
     * Administrador de la plataforma si su correo está en PLATFORM_ADMIN_EMAILS
     * (config accounts.platform_admins). Se llama en cada inicio de sesión.
     */
    public function syncPlatformAdmin(): void
    {
        $isPlatformAdmin = in_array(strtolower($this->email), config('accounts.platform_admins'), true);

        if ($this->is_platform_admin !== $isPlatformAdmin) {
            $this->forceFill(['is_platform_admin' => $isPlatformAdmin])->save();
        }
    }

    /**
     * Negocios de los que la persona es o fue miembro.
     */
    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(Account::class)
            ->using(AccountMember::class)
            ->withPivot(['id', 'status', 'revoked_at', 'revoked_by'])
            ->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(AccountMember::class);
    }

    public function currentAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'current_account_id');
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function operationalExpenses(): HasMany
    {
        return $this->hasMany(OperationalExpense::class);
    }

    /**
     * Determine if the user has left any operational trace in the system,
     * in which case it must be soft deleted instead of permanently removed.
     */
    public function hasActivity(): bool
    {
        return $this->rentals()->withTrashed()->exists()
            || $this->sales()->withTrashed()->exists()
            || $this->purchases()->withTrashed()->exists()
            || $this->inventoryMovements()->exists()
            || $this->cashMovements()->exists()
            || $this->operationalExpenses()->exists();
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    public function person()
    {
        return $this->belongsTo(Person::class);
    }
}
