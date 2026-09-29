<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-brand-navy">
        <div class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">
            <!-- Panel de marca -->
            <div class="relative hidden h-full flex-col overflow-hidden bg-brand-navy p-10 text-white lg:flex dark:border-e dark:border-white/5">
                <div class="pointer-events-none absolute -left-40 -top-40 size-[34rem] rounded-full bg-brand-600/35 blur-3xl"></div>
                <div class="pointer-events-none absolute -bottom-48 -right-32 size-[30rem] rounded-full bg-brand-cyan/20 blur-3xl"></div>
                <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_1px_1px,rgba(255,255,255,0.06)_1px,transparent_0)] bg-[length:22px_22px]"></div>

                <a href="{{ route('home') }}" class="relative z-20" wire:navigate>
                    <x-brand.logo tone="dark" size="lg" />
                </a>

                <div class="relative z-20 mt-auto max-w-lg space-y-6">
                    <div>
                        <p class="text-4xl font-semibold leading-tight tracking-tight">
                            Alquila, vende y controla
                            <span class="bg-gradient-to-r from-brand-cyan to-brand-500 bg-clip-text text-transparent">cada prenda</span>.
                        </p>
                        <p class="mt-4 text-sm leading-6 text-slate-300">
                            Desde la compra hasta su último alquiler: stock por talla y color, devoluciones, mantenimientos
                            y el margen real de cada venta, en un solo sistema.
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-3 text-sm">
                        @foreach ([
                            ['sparkles', 'Alquileres y devoluciones'],
                            ['shopping-cart', 'Ventas con IGV incluido'],
                            ['qr-code', 'Seguimiento por prenda'],
                            ['chart-bar', 'Ganancia real del mes'],
                        ] as [$icon, $feature])
                            <div class="flex items-center gap-2 rounded-xl border border-white/10 bg-white/5 px-3 py-2 backdrop-blur">
                                <flux:icon :icon="$icon" class="size-4 text-brand-400" />
                                <span class="text-slate-200">{{ $feature }}</span>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex items-center gap-3 border-t border-white/10 pt-6 text-xs text-slate-400">
                        <span>Un producto de</span>
                        <img src="{{ asset('brand/kipusoft-horizontal-dark.svg') }}" alt="Kipusoft" class="h-6">
                    </div>
                </div>
            </div>

            <!-- Formulario -->
            <div class="w-full lg:p-8">
                <div class="mx-auto flex w-full flex-col justify-center space-y-8 sm:w-[380px]">
                    <a href="{{ route('home') }}" class="z-20 flex justify-center lg:hidden" wire:navigate>
                        <x-brand.logo size="md" />
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
