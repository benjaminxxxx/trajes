<?php

namespace App\Services\Core;

use App\Models\Account;
use App\Models\AccountMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Único punto de escritura de cuentas y miembros.
 *
 * Reglas que protegen al dueño (el error clásico de "un admin borró al dueño"):
 * - El dueño siempre es admin: nadie le cambia el rol ni lo retira.
 * - Nadie se retira ni se quita el rol de admin a sí mismo.
 * - La propiedad solo la transfiere el propio dueño a otro admin activo.
 * - Un administrador no toca el usuario de nadie (correo, contraseña): solo su
 *   pertenencia y su rol en este negocio.
 */
class AccountService
{
    public const ROLES = [
        'admin' => 'Administrador',
        'vendedor' => 'Vendedor',
    ];

    public function __construct(protected CurrentAccount $currentAccount) {}

    /**
     * @param  array{name: string, ruc?: ?string, address?: ?string, phone?: ?string}  $data
     */
    public function createAccount(User $owner, array $data, string $status = Account::STATUS_TRIAL): Account
    {
        return DB::transaction(function () use ($owner, $data, $status) {
            $account = new Account($data);
            $account->owner_id = $owner->id;
            $account->status = $status;
            $account->trial_ends_at = $status === Account::STATUS_TRIAL ? now()->addDays(config('accounts.trial_days')) : null;
            $account->save();

            $this->addMember($account, $owner, 'admin');
            $owner->forceFill(['current_account_id' => $account->id])->save();

            return $account;
        });
    }

    /**
     * Agrega (o reincorpora) a una persona al negocio con un rol.
     */
    public function addMember(Account $account, User $user, string $role): AccountMember
    {
        $this->ensureValidRole($role);

        return DB::transaction(function () use ($account, $user, $role) {
            $membership = AccountMember::firstOrNew(['account_id' => $account->id, 'user_id' => $user->id]);
            $membership->fill(['status' => AccountMember::STATUS_ACTIVE, 'revoked_at' => null, 'revoked_by' => null])->save();

            $this->assignRole($account, $user, $role);

            return $membership;
        });
    }

    /**
     * Invita por correo. Si la persona ya usa el sistema (en este u otro
     * negocio) se le da acceso con su misma cuenta; si no, se le crea y recibe
     * un correo para definir su propia contraseña.
     */
    public function inviteByEmail(Account $account, string $name, string $email, string $role): User
    {
        [$user, $isNew] = $this->findOrCreatePerson($name, $email);

        $this->addMember($account, $user, $role);

        if ($isNew) {
            $user->forceFill(['current_account_id' => $account->id])->save();
            Password::broker()->sendResetLink(['email' => $user->email]);
        }

        return $user;
    }

    /**
     * Desde la plataforma: registra el negocio de un cliente con su dueño. Si
     * el dueño es nuevo recibe un correo para crear su contraseña (o puede
     * entrar directo con Google usando ese correo).
     *
     * @param  array{name: string, ruc?: ?string}  $data
     */
    public function createAccountForClient(array $data, string $ownerName, string $ownerEmail): Account
    {
        return DB::transaction(function () use ($data, $ownerName, $ownerEmail) {
            [$owner, $isNew] = $this->findOrCreatePerson($ownerName, $ownerEmail);

            $account = $this->createAccount($owner, $data, Account::STATUS_ACTIVE);

            if ($isNew) {
                Password::broker()->sendResetLink(['email' => $owner->email]);
            }

            return $account;
        });
    }

    /**
     * Desde la plataforma: cambia el dueño de un negocio por correo. La persona
     * pasa a ser administradora (si no era miembro, se le da acceso) y dueña.
     */
    public function changeOwner(Account $account, string $name, string $email, User $platformAdmin): User
    {
        return DB::transaction(function () use ($account, $name, $email, $platformAdmin) {
            $newOwner = $this->inviteByEmail($account, $name, $email, 'admin');
            $this->transferOwnership($account, $newOwner, $platformAdmin);

            return $newOwner;
        });
    }

    /**
     * Nadie del negocio (ni sus vendedores) puede entrar mientras esté bloqueado.
     */
    public function block(Account $account, string $reason): void
    {
        $account->forceFill([
            'status' => Account::STATUS_BLOCKED,
            'blocked_at' => now(),
            'blocked_reason' => $reason,
        ])->save();
    }

    public function unblock(Account $account): void
    {
        $account->forceFill([
            'status' => Account::STATUS_ACTIVE,
            'blocked_at' => null,
            'blocked_reason' => null,
        ])->save();
    }

    /**
     * @return array{0: User, 1: bool} la persona y si se acaba de crear
     */
    protected function findOrCreatePerson(string $name, string $email): array
    {
        $email = Str::lower(trim($email));
        $user = User::withTrashed()->where('email', $email)->first();

        if (! $user) {
            $user = new User;
            $user->forceFill([
                'name' => trim($name),
                'email' => $email,
                'password' => Str::password(32),
                'email_verified_at' => now(),
            ])->save();

            return [$user, true];
        }

        if ($user->trashed()) {
            $user->restore();
        }

        return [$user, false];
    }

    public function changeRole(Account $account, User $member, string $role, User $actor): void
    {
        $this->ensureValidRole($role);
        $this->ensureCanManage($account, $member, $actor);

        $this->assignRole($account, $member, $role);
    }

    /**
     * Retira a la persona del negocio: pierde el acceso a este negocio, pero
     * conserva su usuario, su historial aquí y sus otros negocios.
     */
    public function revoke(Account $account, User $member, User $actor): void
    {
        $this->ensureCanManage($account, $member, $actor);

        DB::transaction(function () use ($account, $member, $actor) {
            AccountMember::where('account_id', $account->id)->where('user_id', $member->id)->update([
                'status' => AccountMember::STATUS_REVOKED,
                'revoked_at' => now(),
                'revoked_by' => $actor->id,
            ]);

            $this->currentAccount->runAs($account, function () use ($member) {
                $member->unsetRelation('roles')->syncRoles([]);
            });

            if ((int) $member->current_account_id === $account->id) {
                $member->forceFill(['current_account_id' => null])->save();
            }
        });
    }

    public function restore(Account $account, User $member, string $role = 'vendedor'): void
    {
        $this->addMember($account, $member, $role);
    }

    /**
     * El dueño cede la propiedad a otro administrador activo. El dueño
     * anterior sigue como administrador.
     */
    public function transferOwnership(Account $account, User $newOwner, User $actor): void
    {
        if (! $account->isOwnedBy($actor) && ! $actor->is_platform_admin) {
            throw new \DomainException('Solo el dueño puede transferir la propiedad del negocio.');
        }

        if (! $this->isActiveMember($account, $newOwner) || $this->roleIn($account, $newOwner) !== 'admin') {
            throw new \DomainException('La propiedad solo se puede transferir a un administrador activo del negocio.');
        }

        $account->owner_id = $newOwner->id;
        $account->save();
    }

    public function isActiveMember(Account $account, User $user): bool
    {
        return AccountMember::active()->where('account_id', $account->id)->where('user_id', $user->id)->exists();
    }

    public function roleIn(Account $account, User $user): ?string
    {
        return $this->currentAccount->runAs($account, fn () => $user->unsetRelation('roles')->roles->first()?->name);
    }

    protected function assignRole(Account $account, User $user, string $role): void
    {
        $this->currentAccount->runAs($account, function () use ($user, $role) {
            $user->unsetRelation('roles')->syncRoles([$role]);
        });
    }

    protected function ensureCanManage(Account $account, User $member, User $actor): void
    {
        if ($account->isOwnedBy($member)) {
            throw new \DomainException('El dueño del negocio no se puede modificar ni retirar.');
        }

        if ($member->is($actor)) {
            throw new \DomainException('No puedes cambiar tu propio rol ni retirarte a ti mismo.');
        }

        if (! AccountMember::where('account_id', $account->id)->where('user_id', $member->id)->exists()) {
            throw new \DomainException('Esa persona no pertenece a este negocio.');
        }
    }

    protected function ensureValidRole(string $role): void
    {
        if (! array_key_exists($role, self::ROLES)) {
            throw new \InvalidArgumentException("Rol no válido: {$role}");
        }
    }
}
