<?php

namespace App\Services\Core;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Contracts\PermissionsTeamResolver;

/**
 * Para spatie/permission, el "team" es el negocio actual (CurrentAccount),
 * así hasRole('admin') o can('ventas.anular') se evalúan en ese negocio.
 */
class AccountTeamResolver implements PermissionsTeamResolver
{
    protected int|string|null $overriddenId = null;

    public function setPermissionsTeamId(int|string|Model|null $id): void
    {
        $this->overriddenId = $id instanceof Model ? $id->getKey() : $id;
    }

    public function getPermissionsTeamId(): int|string|null
    {
        return $this->overriddenId ?? app(CurrentAccount::class)->id();
    }
}
