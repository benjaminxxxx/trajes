<x-layouts::app.sidebar :title="$title ?? null">
    {{-- flux:main debe ser hermano directo del sidebar; los halos van dentro. --}}
    <flux:main class="relative overflow-hidden lg:pb-28">
        <!-- Halo azul de marca (esquina superior izquierda) -->
        <div class="pointer-events-none absolute -left-32 -top-36 size-[28rem] rounded-full bg-brand-300/25 blur-3xl dark:bg-brand-600/20"></div>

        <!-- Halo cian (esquina inferior derecha) -->
        <div class="pointer-events-none absolute -bottom-40 -right-24 size-[30rem] rounded-full bg-sky-300/20 blur-3xl dark:bg-sky-500/10"></div>

        <!-- Contenido principal -->
        <div class="relative">
            {{ $slot }}
        </div>
    </flux:main>
</x-layouts::app.sidebar>
