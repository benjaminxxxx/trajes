<div
    class="rounded-[2rem] border border-white/80 bg-white/70 p-5 shadow-xl shadow-slate-900/[0.04] backdrop-blur-xl sm:p-8 dark:border-slate-800 dark:bg-slate-900/75">

    <!-- Header -->
    <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div class="space-y-4">
            <flux:breadcrumbs>
                <flux:breadcrumbs.item :href="route('dashboard')" icon="home" />
                <flux:breadcrumbs.item>Inventario</flux:breadcrumbs.item>
                <flux:breadcrumbs.item :href="route('maintenance.history.index')">Mantenimientos</flux:breadcrumbs.item>
            </flux:breadcrumbs>

            <div>
                <flux:heading size="xl">Mantenimientos</flux:heading>
                <flux:text class="mt-1.5">
                    Lavado, arreglos y planchado de cada prenda. Su costo se suma al historial de la prenda y al dashboard.
                </flux:text>
            </div>
        </div>

        @can('mantenimiento.registrar')
            <flux:button wire:click="$dispatch('open-maintenance-modal')" variant="primary" icon="plus">
                Registrar mantenimiento
            </flux:button>
        @endcan
    </div>

    <!-- Resumen del mes -->
    <div class="mt-7 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl bg-zinc-50 p-4 dark:bg-zinc-800/60">
            <p class="text-xs text-zinc-500">Gastado en {{ $monthLabel }}</p>
            <p class="mt-1 text-xl font-semibold tabular-nums text-red-600 dark:text-red-400">S/ {{ number_format($totals['cost'], 2) }}</p>
            <p class="text-xs text-zinc-500">{{ $totals['count'] }} {{ $totals['count'] === 1 ? 'mantenimiento' : 'mantenimientos' }}</p>
        </div>
        <div class="rounded-2xl bg-zinc-50 p-4 dark:bg-zinc-800/60">
            <p class="text-xs text-zinc-500">Cobrado por daños</p>
            <p class="mt-1 text-xl font-semibold tabular-nums text-emerald-600 dark:text-emerald-400">S/ {{ number_format($totals['charged'], 2) }}</p>
            <p class="text-xs text-zinc-500">A clientes, al recepcionar alquileres</p>
        </div>
        @php($balance = $totals['charged'] - $totals['cost'])
        <div class="rounded-2xl bg-violet-50 p-4 dark:bg-violet-950/30">
            <p class="text-xs text-violet-700 dark:text-violet-300">Cobrado − gastado</p>
            <p class="mt-1 text-xl font-semibold tabular-nums {{ $balance >= 0 ? 'text-violet-900 dark:text-white' : 'text-red-600' }}">S/ {{ number_format($balance, 2) }}</p>
            <p class="text-xs text-violet-700/80 dark:text-violet-300/80">Resultado de mantenimiento del mes</p>
        </div>
        <div class="rounded-2xl bg-zinc-50 p-4 dark:bg-zinc-800/60">
            <p class="text-xs text-zinc-500">Por tipo</p>
            <div class="mt-1 space-y-0.5 text-xs">
                @forelse ($totals['by_type'] as $typeKey => $total)
                    <p class="flex justify-between"><span>{{ $types[$typeKey] ?? $typeKey }}</span><span class="tabular-nums">S/ {{ number_format($total, 2) }}</span></p>
                @empty
                    <p class="text-zinc-500">Sin gastos</p>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="mt-5 flex flex-col gap-3 lg:flex-row lg:items-end">
        <flux:input type="month" wire:model.live="month" label="Mes" class="lg:w-44" />
        <flux:select wire:model.live="type" label="Tipo" class="lg:w-48">
            <flux:select.option value="all">Todos</flux:select.option>
            @foreach ($types as $value => $label)
                <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" label="Prenda" placeholder="Código de la prenda..." />
    </div>

    <!-- Tabla -->
    <div class="mt-5 overflow-x-auto rounded-2xl border border-zinc-200 dark:border-zinc-800">
        <table class="w-full min-w-[52rem] text-sm">
            <thead class="bg-zinc-50 text-xs text-zinc-500 dark:bg-zinc-800/60">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold">Fecha</th>
                    <th class="px-4 py-3 text-left font-semibold">Prenda</th>
                    <th class="px-4 py-3 text-left font-semibold">Tipo</th>
                    <th class="px-4 py-3 text-left font-semibold">Proveedor</th>
                    <th class="px-4 py-3 text-left font-semibold">Detalle</th>
                    <th class="px-4 py-3 text-right font-semibold">Costo</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($maintenances as $maintenance)
                    @php($unit = $maintenance->physicalProduct)
                    <tr wire:key="maintenance-{{ $maintenance->id }}">
                        <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ $maintenance->maintenance_date?->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">
                            @can('inventario.seguimiento')
                                <a href="{{ route('inventory.physical-items.index', ['prenda' => $unit?->id]) }}" wire:navigate
                                    class="font-mono text-xs font-semibold text-violet-600 hover:underline dark:text-violet-400">{{ $unit?->code }}</a>
                            @else
                                <span class="font-mono text-xs font-semibold">{{ $unit?->code }}</span>
                            @endcan
                            <p class="text-xs text-zinc-500">{{ $unit?->variant?->product?->name }} · {{ $unit?->variant?->label() }}</p>
                        </td>
                        <td class="px-4 py-3">{{ $types[$maintenance->type] ?? $maintenance->type }}</td>
                        <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ $maintenance->provider_name ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs text-zinc-500">{{ $maintenance->description ?? '—' }}</td>
                        <td class="px-4 py-3 text-right font-semibold tabular-nums">S/ {{ number_format($maintenance->cost, 2) }}</td>
                        <td class="px-4 py-3 text-right">
                            @can('mantenimiento.registrar')
                                <flux:button size="sm" variant="subtle" icon="trash" wire:click="deleteMaintenance({{ $maintenance->id }})"
                                    wire:confirm="¿Eliminar este registro de mantenimiento?" aria-label="Eliminar" />
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-zinc-500">Sin mantenimientos en {{ $monthLabel }}.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6 flex justify-end">
        {{ $maintenances->links() }}
    </div>

    <livewire:maintenance.maintenance-form-modal />
</div>
