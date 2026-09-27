<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-white dark:bg-zinc-800">
    <flux:sidebar sticky collapsible class="bg-zinc-50 dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-700">
        <flux:sidebar.header>
            <flux:sidebar.brand href="#" logo="https://fluxui.dev/img/demo/logo.png"
                logo:dark="https://fluxui.dev/img/demo/dark-mode-logo.png" name="Acme Inc." />
            <flux:sidebar.collapse
                class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
        </flux:sidebar.header>
        <flux:sidebar.nav>
            <!-- Dashboard -->
            <flux:sidebar.item icon="home" href="{{ route('dashboard') }}" :current="request()->routeIs('dashboard')">
                Dashboard
            </flux:sidebar.item>

            <!-- 1. Alquileres -->
            <flux:sidebar.group expandable icon="sparkles" heading="Alquileres"
                :expanded="request()->routeIs('rental.*')">
                <flux:sidebar.item href="{{ route('rental.rentals.index') }}"
                    :current="request()->routeIs('rental.rentals.index')">
                    Lista de Alquileres
                </flux:sidebar.item>
                <flux:sidebar.item href="{{ route('rental.rentals.create') }}"
                    :current="request()->routeIs('rental.rentals.create')">
                    Nuevo Alquiler
                </flux:sidebar.item>
            </flux:sidebar.group>

            <!-- 2. Ventas Directas -->
            <flux:sidebar.group expandable icon="shopping-cart" heading="Ventas"
                :expanded="request()->routeIs('sale.*')">
                <flux:sidebar.item icon="calculator" href="{{ route('sale.pos') }}"
                    :current="request()->routeIs('sale.pos')">
                    Punto de Venta (POS)
                </flux:sidebar.item>
                <flux:sidebar.item href="{{ route('sale.sales.index') }}"
                    :current="request()->routeIs('sale.sales.index')">
                    Historial de Ventas
                </flux:sidebar.item>
            </flux:sidebar.group>

            <!-- 3. Inventario & Catálogos -->
            <flux:sidebar.group expandable icon="archive-box" heading="Inventario"
                :expanded="request()->routeIs('inventory.*')">
                <flux:sidebar.item href="{{ route('inventory.products.index') }}"
                    :current="request()->routeIs('inventory.products.*')">
                    Catálogo de Productos
                </flux:sidebar.item>
                <flux:sidebar.item href="{{ route('inventory.physical-items.index') }}"
                    :current="request()->routeIs('inventory.physical-items.index')">
                    Seguimiento de Prendas
                </flux:sidebar.item>
                <flux:sidebar.item href="{{ route('inventory.categories.index') }}"
                    :current="request()->routeIs('inventory.categories.index')">
                    Categorías
                </flux:sidebar.item>
            </flux:sidebar.group>

            <!-- 4. Compras -->
            <flux:sidebar.group expandable icon="shopping-bag" heading="Compras"
                :expanded="request()->routeIs('purchasing.*')">
                <flux:sidebar.item href="{{ route('purchasing.purchases.index') }}"
                    :current="request()->routeIs('purchasing.purchases.*')">
                    Compras
                </flux:sidebar.item>
                <flux:sidebar.item href="{{ route('purchasing.purchases.create') }}"
                    :current="request()->routeIs('purchasing.purchases.create')">
                    Registrar compra
                </flux:sidebar.item>
                
                <flux:sidebar.item href="{{ route('purchasing.suppliers.index') }}"
                    :current="request()->routeIs('purchasing.suppliers.index')">
                    Proveedores
                </flux:sidebar.item>
            </flux:sidebar.group>

            <!-- 5. Mantenimiento y Lavandería -->
            <flux:sidebar.group expandable icon="truck" heading="Mantenimiento"
                :expanded="request()->routeIs('maintenance.*')">
                <flux:sidebar.item href="{{ route('maintenance.laundry.index') }}"
                    :current="request()->routeIs('maintenance.laundry.index')">
                    Despacho a Lavandería
                </flux:sidebar.item>
                <flux:sidebar.item href="{{ route('maintenance.history.index') }}"
                    :current="request()->routeIs('maintenance.history.index')">
                    Historial de Lavado
                </flux:sidebar.item>
            </flux:sidebar.group>

            <!-- 6. Finanzas y Caja -->
            <flux:sidebar.group expandable icon="banknotes" heading="Finanzas"
                :expanded="request()->routeIs('finance.*')">
                <flux:sidebar.item href="{{ route('finance.cash.index') }}"
                    :current="request()->routeIs('finance.cash.index')">
                    Caja Chica / Arqueo
                </flux:sidebar.item>
                <flux:sidebar.item href="{{ route('finance.payments.index') }}"
                    :current="request()->routeIs('finance.payments.index')">
                    Pagos y Cobros
                </flux:sidebar.item>
                <flux:sidebar.item href="{{ route('finance.expenses.index') }}"
                    :current="request()->routeIs('finance.expenses.index')">
                    Registro de Gastos
                </flux:sidebar.item>
            </flux:sidebar.group>

            <!-- 7. Administración / Core -->
            <flux:sidebar.group expandable icon="cog-6-tooth" heading="Administración"
                :expanded="request()->routeIs('core.*')">
                <flux:sidebar.item href="{{ route('core.clients.index') }}"
                    :current="request()->routeIs('core.clients.index')">
                    Clientes
                </flux:sidebar.item>
                <flux:sidebar.item href="{{ route('core.users.index') }}"
                    :current="request()->routeIs('core.users.index')">
                    Usuarios
                </flux:sidebar.item>
            </flux:sidebar.group>
        </flux:sidebar.nav>
        <flux:sidebar.spacer />
        <flux:sidebar.nav>
            <flux:sidebar.item icon="cog-6-tooth" href="#">Settings</flux:sidebar.item>
            <flux:sidebar.item icon="information-circle" href="#">Help</flux:sidebar.item>
        </flux:sidebar.nav>
        <flux:dropdown position="top" align="start" class="max-lg:hidden">
            <flux:sidebar.profile avatar="https://fluxui.dev/img/demo/user.png" name="Olivia Martin" />
            <flux:menu>
                <flux:menu.radio.group>
                    <flux:menu.radio checked>Olivia Martin</flux:menu.radio>
                    <flux:menu.radio>Truly Delta</flux:menu.radio>
                </flux:menu.radio.group>
                <flux:menu.separator />
                <flux:menu.item icon="arrow-right-start-on-rectangle">Logout</flux:menu.item>
            </flux:menu>
        </flux:dropdown>
    </flux:sidebar>

    <!-- Mobile User Menu -->
    <flux:header class="lg:hidden">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

        <flux:spacer />

        <flux:dropdown position="top" align="end">
            <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" />

            <flux:menu>
                <flux:menu.radio.group>
                    <div class="p-0 text-sm font-normal">
                        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                            <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" />

                            <div class="grid flex-1 text-start text-sm leading-tight">
                                <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                            </div>
                        </div>
                    </div>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <flux:menu.radio.group>
                    <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                        {{ __('Settings') }}
                    </flux:menu.item>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle"
                        class="w-full cursor-pointer" data-test="logout-button">
                        {{ __('Log out') }}
                    </flux:menu.item>
                </form>
            </flux:menu>
        </flux:dropdown>
    </flux:header>

    {{ $slot }}

    @persist('toast')
        <flux:toast.group>
            <flux:toast />
        </flux:toast.group>
    @endpersist

    @fluxScripts
</body>

</html>
