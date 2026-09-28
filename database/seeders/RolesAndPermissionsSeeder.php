<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Sincroniza los permisos y roles definidos en config/permissions.php.
 * Idempotente: se puede volver a correr cada vez que cambie ese archivo.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = array_keys(config('permissions.permissions'));

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Permisos que ya no existen en la configuración (ej. los anteriores
        // 'vender', 'alquilar'...) se eliminan para no dejar basura asignable.
        Permission::where('guard_name', 'web')->whereNotIn('name', $permissions)->delete();

        // admin: sin permisos asignados, el acceso total lo da Gate::before.
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        foreach (config('permissions.roles') as $roleName => $rolePermissions) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web'])
                ->syncPermissions($rolePermissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
