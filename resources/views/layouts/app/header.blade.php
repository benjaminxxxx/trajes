<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-white dark:bg-zinc-950">
    {{-- Menú filtrado por permisos (config/menu.php). El mismo array alimenta escritorio y celular. --}}
    @php($menu = app(\App\Services\Core\NavigationMenu::class)->for(auth()->user()))

    <flux:header container class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:sidebar.toggle class="lg:hidden mr-2" icon="bars-2" inset="left" />

        <x-app-logo href="{{ route('dashboard') }}" wire:navigate />

        <!-- Navegación de escritorio -->
        <flux:navbar class="-mb-px max-lg:hidden">
            @foreach ($menu as $entry)
                @if (isset($entry['items']))
                    <flux:dropdown>
                        <flux:navbar.item :icon="$entry['icon']" icon-trailing="chevron-down" :current="$entry['current']">
                            {{ $entry['label'] }}
                        </flux:navbar.item>
                        <flux:navmenu>
                            @foreach ($entry['items'] as $item)
                                @if ($item['separator'] ?? false)
                                    <flux:navmenu.separator />
                                @endif
                                <flux:navmenu.item :icon="$item['icon']" :href="route($item['route'])"
                                    :current="$item['current']" wire:navigate>
                                    {{ $item['label'] }}
                                </flux:navmenu.item>
                            @endforeach
                        </flux:navmenu>
                    </flux:dropdown>
                @else
                    <flux:navbar.item :icon="$entry['icon']" :href="route($entry['route'])" :current="$entry['current']"
                        wire:navigate>
                        {{ $entry['label'] }}
                    </flux:navbar.item>
                @endif
            @endforeach
        </flux:navbar>

        <flux:spacer />

        <flux:navbar class="me-1.5 space-x-0.5 rtl:space-x-reverse py-0!">
            <flux:button x-data x-on:click="$flux.dark = ! $flux.dark" icon="moon" variant="subtle"
                aria-label="Toggle dark mode" />
        </flux:navbar>

        <x-desktop-user-menu />
    </flux:header>

    <!-- Menú del celular -->
    <flux:sidebar collapsible="mobile" sticky
        class="lg:hidden border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:sidebar.header>
            <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
            <flux:sidebar.collapse
                class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
        </flux:sidebar.header>

        <flux:sidebar.nav>
            @foreach ($menu as $entry)
                @if (isset($entry['items']))
                    <flux:sidebar.group expandable :icon="$entry['icon']" :heading="$entry['label']" :expanded="$entry['current']">
                        @foreach ($entry['items'] as $item)
                            <flux:sidebar.item :icon="$item['icon']" :href="route($item['route'])" :current="$item['current']"
                                wire:navigate>
                                {{ $item['label'] }}
                            </flux:sidebar.item>
                        @endforeach
                    </flux:sidebar.group>
                @else
                    <flux:sidebar.item :icon="$entry['icon']" :href="route($entry['route'])" :current="$entry['current']"
                        wire:navigate>
                        {{ $entry['label'] }}
                    </flux:sidebar.item>
                @endif
            @endforeach
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
