<?php

/*
|--------------------------------------------------------------------------
| Menú principal (sidebar)
|--------------------------------------------------------------------------
|
| Una sola definición para el sidebar de escritorio y el del celular. Cada
| opción declara el permiso que la muestra (el mismo que protege su ruta en
| routes/web.php); una sección sin opciones visibles se oculta.
|
| - route:      nombre de la ruta
| - active:     patrones de ruta que la marcan como actual (por defecto, route)
| - permission: permiso requerido (null = cualquier usuario con sesión)
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
        'label' => 'Ventas',
        'icon' => 'shopping-cart',
        'items' => [
            ['label' => 'Nueva venta', 'icon' => 'shopping-cart', 'route' => 'sale.sales.create', 'permission' => 'ventas.registrar'],
            ['label' => 'Historial de ventas', 'icon' => 'document-text', 'route' => 'sale.sales.index', 'permission' => 'ventas.ver'],
        ],
    ],
    [
        'label' => 'Facturación',
        'icon' => 'receipt-percent',
        'items' => [
            ['label' => 'Comprobantes electrónicos', 'icon' => 'document-check', 'route' => 'billing.documents.index', 'permission' => 'facturacion.emitir'],
            ['label' => 'Configuración', 'icon' => 'link', 'route' => 'billing.settings', 'permission' => 'facturacion.configurar'],
        ],
    ],
    [
        'label' => 'Alquileres',
        'icon' => 'sparkles',
        'items' => [
            ['label' => 'Nuevo alquiler', 'icon' => 'sparkles', 'route' => 'rental.rentals.create', 'permission' => 'alquileres.registrar'],
            ['label' => 'Alquileres', 'icon' => 'clipboard-document-list', 'route' => 'rental.rentals.index', 'active' => ['rental.rentals.index', 'rental.rentals.return'], 'permission' => 'alquileres.ver'],
        ],
    ],
    [
        'label' => 'Inventario',
        'icon' => 'archive-box',
        'items' => [
            ['label' => 'Catálogo de productos', 'icon' => 'tag', 'route' => 'inventory.products.index', 'active' => ['inventory.products.*'], 'permission' => 'inventario.productos'],
            ['label' => 'Categorías', 'icon' => 'folder', 'route' => 'inventory.categories.index', 'permission' => 'inventario.categorias'],
            ['label' => 'Tallas, colores y marcas', 'icon' => 'swatch', 'route' => 'inventory.attributes.index', 'permission' => 'inventario.atributos'],
            ['label' => 'Seguimiento de prendas', 'icon' => 'qr-code', 'route' => 'inventory.physical-items.index', 'permission' => 'inventario.seguimiento'],
            ['label' => 'Mantenimientos', 'icon' => 'wrench-screwdriver', 'route' => 'maintenance.history.index', 'permission' => 'mantenimiento.ver'],
            ['label' => 'Bienes del negocio', 'icon' => 'cube', 'route' => 'inventory.assets.index', 'permission' => 'inventario.bienes'],
            ['label' => 'Valorizado', 'icon' => 'scale', 'route' => 'inventory.valuation.index', 'permission' => 'inventario.valorizado'],
        ],
    ],
    [
        'label' => 'Compras',
        'icon' => 'truck',
        'items' => [
            ['label' => 'Registrar compra', 'icon' => 'plus-circle', 'route' => 'purchasing.purchases.create', 'permission' => 'compras.registrar'],
            ['label' => 'Compras realizadas', 'icon' => 'truck', 'route' => 'purchasing.purchases.index', 'permission' => 'compras.ver'],
        ],
    ],
    [
        'label' => 'Directorio',
        'icon' => 'users',
        'items' => [
            ['label' => 'Clientes', 'icon' => 'users', 'route' => 'core.clients.index', 'permission' => 'clientes.ver'],
            ['label' => 'Proveedores', 'icon' => 'building-office', 'route' => 'purchasing.suppliers.index', 'permission' => 'proveedores.ver'],
        ],
    ],
    [
        'label' => 'Finanzas',
        'icon' => 'banknotes',
        'items' => [
            ['label' => 'Gastos operativos', 'icon' => 'receipt-percent', 'route' => 'finance.expenses.index', 'permission' => 'finanzas.gastos'],
        ],
    ],
    [
        'label' => 'Administración',
        'icon' => 'cog-6-tooth',
        'items' => [
            ['label' => 'Usuarios', 'icon' => 'user-group', 'route' => 'core.users.index', 'permission' => 'seguridad.usuarios'],
            ['label' => 'Configuración', 'icon' => 'adjustments-horizontal', 'route' => 'core.settings.index', 'permission' => 'configuracion.general'],
        ],
    ],
];
