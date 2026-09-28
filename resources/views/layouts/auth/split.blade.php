<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">
            <div class="relative hidden h-full flex-col overflow-hidden p-10 text-white lg:flex dark:border-e dark:border-neutral-800">
                <!-- Fondo de marca -->
                <div class="absolute inset-0 bg-gradient-to-br from-violet-700 via-violet-900 to-neutral-950"></div>
                <div class="pointer-events-none absolute -right-24 -top-24 size-96 rounded-full bg-fuchsia-500/30 blur-3xl"></div>
                <div class="pointer-events-none absolute -bottom-32 -left-16 size-96 rounded-full bg-cyan-400/20 blur-3xl"></div>

                <a href="{{ route('home') }}" class="relative z-20 flex items-center text-lg font-medium" wire:navigate>
                    <span class="flex h-10 w-10 items-center justify-center rounded-md">
                        <x-app-logo-icon class="me-2 h-7 fill-current text-white" />
                    </span>
                    {{ config('app.name', 'Laravel') }}
                </a>

                <div class="relative z-20 mt-auto space-y-6">
                    <div>
                        <p class="text-3xl font-semibold leading-tight">Alquiler y venta de trajes,<br>todo en un solo lugar.</p>
                        <p class="mt-3 max-w-md text-sm text-violet-100/80">
                            Controla cada prenda desde su compra hasta su último alquiler: stock por talla y color,
                            devoluciones, mantenimientos y el margen real de cada venta.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2 text-xs">
                        @foreach (['Ventas y alquileres', 'Seguimiento por prenda', 'Márgenes reales'] as $feature)
                            <span class="rounded-full border border-white/20 bg-white/10 px-3 py-1 backdrop-blur">{{ $feature }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="w-full lg:p-8">
                <div class="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]">
                    <a href="{{ route('home') }}" class="z-20 flex flex-col items-center gap-2 font-medium lg:hidden" wire:navigate>
                        <span class="flex h-9 w-9 items-center justify-center rounded-md">
                            <x-app-logo-icon class="size-9 fill-current text-black dark:text-white" />
                        </span>

                        <span class="sr-only">{{ config('app.name', 'Laravel') }}</span>
                    </a>
                    {{ $slot }}
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
