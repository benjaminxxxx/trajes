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

        // Facturación electrónica
        'facturacion.emitir' => 'Emitir facturas y boletas electrónicas de las ventas',
        'facturacion.configurar' => 'Configurar la conexión con el servicio de facturación',

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
        'inventario.atributos' => 'Gestionar tallas, colores y marcas',
        'inventario.seguimiento' => 'Ver el seguimiento de prendas',
        'inventario.bienes' => 'Gestionar los bienes del negocio (mobiliario, consumibles...)',
        'inventario.valorizado' => 'Ver el valorizado de la empresa',

        // Catálogo para clientes
        'catalogo.ver' => 'Ver el catálogo de prendas y compartirlo por WhatsApp',
        'catalogo.costos' => 'Ver en el catálogo el costo, la ganancia y el proveedor',

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
        'finanzas.gastos' => 'Gastos operativos',
        'caja.ver' => 'Ver la caja y sus movimientos',
        'caja.registrar' => 'Registrar aportes, retiros y anulaciones en caja',

        // Seguridad
        'seguridad.usuarios' => 'Gestionar usuarios',
        'auditoria.ver' => 'Ver la auditoría de ediciones y eliminaciones',
        'configuracion.general' => 'Configuración del sistema (desgaste de prendas)',
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
            'catalogo.ver',
            'catalogo.costos',
        ],
    ],

];
