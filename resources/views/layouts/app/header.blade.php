<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-white dark:bg-zinc-950">
    <flux:header container class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:sidebar.toggle class="lg:hidden mr-2" icon="bars-2" inset="left" />

        <x-app-logo href="{{ route('dashboard') }}" wire:navigate />

        <!-- Navegación Principal Simplificada (4 Menús) -->
        <flux:navbar class="-mb-px max-lg:hidden">
            <!-- Dashboard -->
            <flux:navbar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')"
                wire:navigate>
                Dashboard
            </flux:navbar.item>

            <!-- 1. Operaciones / Comercial -->
            <flux:dropdown>
                <flux:navbar.item icon="shopping-bag" icon-trailing="chevron-down"
                    :current="request()->routeIs('rental.*', 'sale.*')">
                    Comercial
                </flux:navbar.item>
                <flux:navmenu>
                    <flux:navmenu.item icon="sparkles" href="{{ route('rental.rentals.index') }}"
                        :current="request()->routeIs('rental.rentals.*')" wire:navigate>
                        Alquileres
                    </flux:navmenu.item>
                    
                    <flux:navmenu.item icon="calculator" href="{{ route('rental.rentals.create') }}"
                        :current="request()->routeIs('rental.rentals.create')" wire:navigate>
                        Punto de Alquiler
                    </flux:navmenu.item>
                    <flux:navmenu.item icon="calculator" href="{{ route('sale.pos') }}"
                        :current="request()->routeIs('sale.pos')" wire:navigate>
                        Punto de Venta (POS)
                    </flux:navmenu.item>
                    <flux:navmenu.item icon="document-text" href="{{ route('sale.sales.index') }}"
                        :current="request()->routeIs('sale.sales.index')" wire:navigate>
                        Historial de Ventas
                    </flux:navmenu.item>
                </flux:navmenu>
            </flux:dropdown>

            <!-- 2. Inventario y Compras -->
            <flux:dropdown>
                <flux:navbar.item icon="archive-box" icon-trailing="chevron-down"
                    :current="request()->routeIs('inventory.*', 'purchasing.*')">
                    Inventario
                </flux:navbar.item>
                <flux:navmenu>
                    <flux:navmenu.item icon="tag" href="{{ route('inventory.products.index') }}"
                        :current="request()->routeIs('inventory.products.*')" wire:navigate>
                        Catálogo de Productos
                    </flux:navmenu.item>
                    <flux:navmenu.item icon="qr-code" href="{{ route('inventory.physical-items.index') }}"
                        :current="request()->routeIs('inventory.physical-items.index')" wire:navigate>
                        Seguimiento de Prendas
                    </flux:navmenu.item>
                    <flux:navmenu.item icon="folder" href="{{ route('inventory.categories.index') }}"
                        :current="request()->routeIs('inventory.categories.index')" wire:navigate>
                        Categorías
                    </flux:navmenu.item>
                    <flux:navmenu.separator />
                    <flux:navmenu.item icon="shopping-cart" href="{{ route('purchasing.purchases.create') }}"
                        :current="request()->routeIs('purchasing.purchases.*')" wire:navigate>
                        Registrar compra
                    </flux:navmenu.item>
                    <flux:navmenu.item icon="shopping-cart" href="{{ route('purchasing.purchases.index') }}"
                        :current="request()->routeIs('purchasing.purchases.*')" wire:navigate>
                        Compras realizadas
                    </flux:navmenu.item>
                    <flux:navmenu.item icon="building-office" href="{{ route('purchasing.suppliers.index') }}"
                        :current="request()->routeIs('purchasing.suppliers.index')" wire:navigate>
                        Proveedores
                    </flux:navmenu.item>
                </flux:navmenu>
            </flux:dropdown>

            <!-- 3. Mantenimiento / Lavandería -->
            <flux:dropdown>
                <flux:navbar.item icon="truck" icon-trailing="chevron-down"
                    :current="request()->routeIs('maintenance.*')">
                    Mantenimiento
                </flux:navbar.item>
                <flux:navmenu>
                    <flux:navmenu.item icon="arrow-path" href="{{ route('maintenance.laundry.index') }}"
                        :current="request()->routeIs('maintenance.laundry.index')" wire:navigate>
                        Despacho a Lavandería
                    </flux:navmenu.item>
                    <flux:navmenu.item icon="clock" href="{{ route('maintenance.history.index') }}"
                        :current="request()->routeIs('maintenance.history.index')" wire:navigate>
                        Historial de Lavado
                    </flux:navmenu.item>
                </flux:navmenu>
            </flux:dropdown>

            <!-- 4. Administración y Finanzas -->
            <flux:dropdown>
                <flux:navbar.item icon="cog-6-tooth" icon-trailing="chevron-down"
                    :current="request()->routeIs('finance.*', 'core.*')">
                    Administración
                </flux:navbar.item>
                <flux:navmenu>
                    <flux:navmenu.item icon="banknotes" href="{{ route('finance.cash.index') }}"
                        :current="request()->routeIs('finance.cash.index')" wire:navigate>
                        Caja Chica / Arqueo
                    </flux:navmenu.item>
                    <flux:navmenu.item icon="credit-card" href="{{ route('finance.payments.index') }}"
                        :current="request()->routeIs('finance.payments.index')" wire:navigate>
                        Pagos y Cobros
                    </flux:navmenu.item>
                    <flux:navmenu.item icon="receipt-percent" href="{{ route('finance.expenses.index') }}"
                        :current="request()->routeIs('finance.expenses.index')" wire:navigate>
                        Gastos
                    </flux:navmenu.item>
                    <flux:navmenu.separator />
                    <flux:navmenu.item icon="users" href="{{ route('core.clients.index') }}"
                        :current="request()->routeIs('core.clients.index')" wire:navigate>
                        Clientes
                    </flux:navmenu.item>
                    <flux:navmenu.item icon="user-group" href="{{ route('core.users.index') }}"
                        :current="request()->routeIs('core.users.index')" wire:navigate>
                        Usuarios del Sistema
                    </flux:navmenu.item>
                </flux:navmenu>
            </flux:dropdown>
        </flux:navbar>

        <flux:spacer />

        <flux:navbar class="me-1.5 space-x-0.5 rtl:space-x-reverse py-0!">
            <flux:button x-data x-on:click="$flux.dark = ! $flux.dark" icon="moon" variant="subtle"
                aria-label="Toggle dark mode" />
        </flux:navbar>

        <x-desktop-user-menu />
    </flux:header>

    <!-- Mobile Menu -->
    <flux:sidebar collapsible="mobile" sticky
        class="lg:hidden border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:sidebar.header>
            <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
            <flux:sidebar.collapse
                class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
        </flux:sidebar.header>

        <flux:sidebar.nav>
            <flux:sidebar.group :heading="__('Platform')">
                <flux:sidebar.item icon="layout-grid" :href="route('dashboard')"
                    :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:sidebar.item>
            </flux:sidebar.group>
        </flux:sidebar.nav>
    </flux:sidebar>

    {{ $slot }}

    @persist('toast')
        <flux:toast.group>
            <flux:toast />
        </flux:toast.group>
    @endpersist

    @fluxScripts
</body>

</html>
