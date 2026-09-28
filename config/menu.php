<?php

/*
|--------------------------------------------------------------------------
| Menú principal
|--------------------------------------------------------------------------
|
| Una sola definición para el header de escritorio y el menú lateral del
| celular. Cada opción declara el permiso que la muestra (el mismo que
| protege su ruta en routes/web.php); un grupo sin opciones visibles se oculta.
|
| - route:      nombre de la ruta
| - active:     patrones de ruta que la marcan como actual (por defecto, route)
| - permission: permiso requerido (null = cualquier usuario con sesión)
| - separator:  true para dibujar un separador antes de la opción
|
*/

return [
    [
        'label' => 'Dashboard',
        'icon' => 'home',
        'route' => 'dashboard',
        'permission' => null,
    ],
    [
        'label' => 'Comercial',
        'icon' => 'shopping-bag',
        'items' => [
            ['label' => 'Nueva venta', 'icon' => 'calculator', 'route' => 'sale.sales.create', 'permission' => 'ventas.registrar'],
            ['label' => 'Ventas', 'icon' => 'document-text', 'route' => 'sale.sales.index', 'permission' => 'ventas.ver'],
            ['label' => 'Nuevo alquiler', 'icon' => 'sparkles', 'route' => 'rental.rentals.create', 'permission' => 'alquileres.registrar', 'separator' => true],
            ['label' => 'Alquileres', 'icon' => 'clipboard-document-list', 'route' => 'rental.rentals.index', 'active' => ['rental.rentals.index', 'rental.rentals.return'], 'permission' => 'alquileres.ver'],
            ['label' => 'Clientes', 'icon' => 'users', 'route' => 'core.clients.index', 'permission' => 'clientes.ver', 'separator' => true],
        ],
    ],
    [
        'label' => 'Inventario',
        'icon' => 'archive-box',
        'items' => [
            ['label' => 'Catálogo de productos', 'icon' => 'tag', 'route' => 'inventory.products.index', 'active' => ['inventory.products.*'], 'permission' => 'inventario.productos'],
            ['label' => 'Seguimiento de prendas', 'icon' => 'qr-code', 'route' => 'inventory.physical-items.index', 'permission' => 'inventario.seguimiento'],
            ['label' => 'Categorías', 'icon' => 'folder', 'route' => 'inventory.categories.index', 'permission' => 'inventario.categorias'],
            ['label' => 'Mantenimientos', 'icon' => 'wrench-screwdriver', 'route' => 'maintenance.history.index', 'permission' => 'mantenimiento.ver', 'separator' => true],
        ],
    ],
    [
        'label' => 'Compras',
        'icon' => 'truck',
        'items' => [
            ['label' => 'Registrar compra', 'icon' => 'plus', 'route' => 'purchasing.purchases.create', 'permission' => 'compras.registrar'],
            ['label' => 'Compras realizadas', 'icon' => 'shopping-cart', 'route' => 'purchasing.purchases.index', 'permission' => 'compras.ver'],
            ['label' => 'Proveedores', 'icon' => 'building-office', 'route' => 'purchasing.suppliers.index', 'permission' => 'proveedores.ver'],
        ],
    ],
    [
        'label' => 'Administración',
        'icon' => 'cog-6-tooth',
        'items' => [
            ['label' => 'Caja chica / arqueo', 'icon' => 'banknotes', 'route' => 'finance.cash.index', 'permission' => 'finanzas.caja'],
            ['label' => 'Pagos y cobros', 'icon' => 'credit-card', 'route' => 'finance.payments.index', 'permission' => 'finanzas.pagos'],
            ['label' => 'Gastos operativos', 'icon' => 'receipt-percent', 'route' => 'finance.expenses.index', 'permission' => 'finanzas.gastos'],
            ['label' => 'Usuarios', 'icon' => 'user-group', 'route' => 'core.users.index', 'permission' => 'seguridad.usuarios', 'separator' => true],
        ],
    ],
];
