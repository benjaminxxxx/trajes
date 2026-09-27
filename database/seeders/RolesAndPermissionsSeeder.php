<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Limpiar caché de permisos antes de crear/asignar
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'vender',
            'alquilar',
            'registrar compra',
            'registrar proveedor',
            'registrar categorias de productos',
            'registrar clientes',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        // Rol admin: NO se le asignan permisos uno por uno.
        // El bypass general se hace vía Gate::before() en
        // App\Providers\AppServiceProvider (ver nota abajo).
        Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        // Rol vendedor: con el set de permisos operativos
        $vendedorRole = Role::firstOrCreate([
            'name' => 'vendedor',
            'guard_name' => 'web',
        ]);

        $vendedorRole->syncPermissions($permissions);
    }
}