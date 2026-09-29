<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-white antialiased dark:bg-zinc-950">
    {{-- Menú y accesos directos filtrados por permisos (config/menu.php). --}}
    @php
        $navigation = app(\App\Services\Core\NavigationMenu::class);
        $menu = $navigation->for(auth()->user());
        $quickActions = $navigation->quickActions(auth()->user());
    @endphp

    <flux:sidebar sticky collapsible
        class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900">
        <flux:sidebar.header>
            <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
            <flux:sidebar.collapse
                class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
        </flux:sidebar.header>

        <flux:sidebar.nav>
            @foreach ($menu as $entry)
                @if (isset($entry['items']))
                    <flux:sidebar.group expandable :icon="$entry['icon']" :heading="$entry['label']"
                        :expanded="$entry['current']" class="grid">
                        @foreach ($entry['items'] as $item)
                            <flux:sidebar.item :icon="$item['icon']" :href="route($item['route'])"
                                :current="$item['current']" wire:navigate>
                                {{ $item['label'] }}
                            </flux:sidebar.item>
                        @endforeach
                    </flux:sidebar.group>
                @else
                    <flux:sidebar.item :icon="$entry['icon']" :href="route($entry['route'])"
                        :current="$entry['current']" wire:navigate>
                        {{ $entry['label'] }}
                    </flux:sidebar.item>
                @endif
            @endforeach
        </flux:sidebar.nav>

        <flux:sidebar.spacer />

        <flux:sidebar.nav>
            <flux:sidebar.item icon="moon" x-data x-on:click.prevent="$flux.dark = ! $flux.dark" href="#">
                Modo oscuro
            </flux:sidebar.item>
        </flux:sidebar.nav>

        <div class="max-lg:hidden">
            <x-desktop-user-menu />
        </div>
    </flux:sidebar>

    <!-- Barra superior de tablet y celular -->
    <flux:header class="border-b border-zinc-200 bg-zinc-50 lg:hidden dark:border-zinc-800 dark:bg-zinc-900">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

        <a href="{{ route('dashboard') }}" wire:navigate class="ms-2 max-sm:hidden" aria-label="Kipusoft Alquiler">
            <x-brand.logo size="sm" />
        </a>
        <a href="{{ route('dashboard') }}" wire:navigate class="ms-2 sm:hidden" aria-label="Kipusoft Alquiler">
            <x-brand.mark class="size-8" />
        </a>

        <flux:spacer />

        {{-- En tablet y celular los accesos directos van fijos en la barra, no flotando. --}}
        @if ($quickActions !== [])
            <div class="me-2 flex items-center gap-2" data-test="quick-actions-bar">
                @foreach ($quickActions as $action)
                    <flux:button size="sm" :variant="$loop->first ? 'primary' : 'filled'" :icon="$action['icon']"
                        :href="route($action['route'])" wire:navigate>
                        {{ $action['label'] }}
                    </flux:button>
                @endforeach
            </div>
        @endif

        <flux:dropdown position="top" align="end">
            <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" />

            <flux:menu>
                <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                    <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" />
                    <div class="grid flex-1 text-start text-sm leading-tight">
                        <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                        <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                    </div>
                </div>

                <flux:menu.separator />

                <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                    {{ __('Settings') }}
                </flux:menu.item>

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

    {{-- En escritorio los accesos directos flotan abajo a la derecha. --}}
    @if ($quickActions !== [])
        <div class="fixed bottom-6 right-6 z-40 flex flex-col items-end gap-3 max-lg:hidden" data-test="quick-actions-floating">
            @foreach ($quickActions as $action)
                <a href="{{ route($action['route']) }}" wire:navigate
                    @class([
                        'group flex items-center gap-2 rounded-full py-3 pe-5 ps-4 text-sm font-semibold shadow-lg transition hover:-translate-y-0.5 hover:shadow-xl',
                        'bg-brand-600 text-white shadow-brand-600/30 hover:bg-brand-700' => $loop->first,
                        'bg-white text-brand-700 ring-1 ring-brand-200 hover:bg-brand-50 dark:bg-zinc-900 dark:text-brand-300 dark:ring-brand-800 dark:hover:bg-zinc-800' => ! $loop->first,
                    ])>
                    <flux:icon :icon="$action['icon']" class="size-5" />
                    {{ $action['label'] }}
                </a>
            @endforeach
        </div>
    @endif

    @persist('toast')
        <flux:toast.group>
            <flux:toast />
        </flux:toast.group>
    @endpersist

    @fluxScripts
</body>

</html>
