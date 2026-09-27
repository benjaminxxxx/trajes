<x-layouts::app.header :title="$title ?? null">
    <div class="relative min-h-screen overflow-hidden">
        <!-- Halo Fucsia/Morado (Esquina Superior Izquierda) -->
        <div class="pointer-events-none absolute -left-32 -top-36 size-[28rem] rounded-full bg-fuchsia-300/25 blur-3xl dark:bg-fuchsia-600/20"></div>

        <!-- Halo Cian/Azul (Esquina Inferior Derecha) -->
        <div class="pointer-events-none absolute -bottom-40 -right-24 size-[30rem] rounded-full bg-cyan-300/20 blur-3xl dark:bg-cyan-600/15"></div>

        <!-- Contenido Principal -->
        <flux:main class="relative">
            {{ $slot }}
        </flux:main>
    </div>
</x-layouts::app.header>