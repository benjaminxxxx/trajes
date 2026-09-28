<?php

/*
|--------------------------------------------------------------------------
| Permisos del sistema
|--------------------------------------------------------------------------
|
| Catálogo único de permisos (nombre => descripción). Se usan en las rutas
| (middleware `can:`), en el menú (config/menu.php) y en las acciones de los
| componentes. RolesAndPermissionsSeeder los sincroniza con la base de datos.
|
| El rol `admin` no necesita permisos: tiene acceso total mediante el bypass
| de Gate::before (AppServiceProvider). El resto de roles solo puede lo que
| tenga asignado aquí; más adelante un módulo de roles podrá combinarlos.
|
*/

return [

    'permissions' => [
        // Dashboard
        'dashboard.finanzas' => 'Ver márgenes, gastos y ganancias en el dashboard',

        // Ventas
        'ventas.ver' => 'Ver ventas realizadas',
        'ventas.registrar' => 'Registrar ventas',
        'ventas.editar' => 'Editar y facturar ventas',
        'ventas.anular' => 'Anular ventas',

        // Alquileres
        'alquileres.ver' => 'Ver alquileres realizados',
        'alquileres.registrar' => 'Registrar alquileres',
        'alquileres.recepcionar' => 'Recepcionar alquileres devueltos',
        'alquileres.editar' => 'Editar alquileres',
        'alquileres.anular' => 'Anular alquileres',

        // Clientes
        'clientes.ver' => 'Ver clientes',
        'clientes.registrar' => 'Registrar clientes',
        'clientes.editar' => 'Editar, dar de baja y restaurar clientes',

        // Inventario
        'inventario.productos' => 'Gestionar el catálogo de productos',
        'inventario.categorias' => 'Gestionar categorías',
        'inventario.seguimiento' => 'Ver el seguimiento de prendas',

        // Compras
        'compras.ver' => 'Ver compras',
        'compras.registrar' => 'Registrar compras',
        'compras.editar' => 'Editar compras',
        'proveedores.ver' => 'Ver proveedores',
        'proveedores.registrar' => 'Registrar y editar proveedores',

        // Mantenimiento
        'mantenimiento.ver' => 'Ver mantenimientos de prendas',
        'mantenimiento.registrar' => 'Registrar mantenimientos de prendas',

        // Finanzas
        'finanzas.caja' => 'Caja chica y arqueo',
        'finanzas.pagos' => 'Pagos y cobros',
        'finanzas.gastos' => 'Gastos operativos',

        // Seguridad
        'seguridad.usuarios' => 'Gestionar usuarios',
    ],

    /*
    | Permisos por rol. `admin` no se lista: tiene acceso total.
    */
    'roles' => [
        'vendedor' => [
            'ventas.ver',
            'ventas.registrar',
            'alquileres.ver',
            'alquileres.registrar',
            'alquileres.recepcionar',
            'clientes.ver',
            'clientes.registrar',
        ],
    ],

];
