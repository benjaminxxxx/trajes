<div class="flex flex-col gap-5">
    <!-- Header: fecha consultada y accesos directos -->
    <div
        class="rounded-[2rem] border border-white/80 bg-white/70 p-5 shadow-xl shadow-slate-900/[0.04] backdrop-blur-xl sm:p-8 dark:border-slate-800 dark:bg-slate-900/75">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-xs font-semibold text-violet-600 dark:text-violet-400">Hola, {{ auth()->user()->name }}</p>
                <flux:heading size="xl" class="mt-1">Resumen del negocio</flux:heading>
                <flux:text class="mt-1.5">Totales de {{ strtolower($dayLabel) === 'hoy' ? 'hoy' : 'el ' . $dayLabel }}, de {{ $monthLabel }} y del año.</flux:text>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex items-end gap-1">
                    <flux:button icon="chevron-left" variant="subtle" wire:click="shiftDay(-1)" aria-label="Día anterior" />
                    <flux:input type="date" wire:model.live="date" label="Fecha" class="w-44" />
                    <flux:button icon="chevron-right" variant="subtle" wire:click="shiftDay(1)" aria-label="Día siguiente" />
                    @if ($dayLabel !== 'Hoy')
                        <flux:button size="sm" variant="outline" wire:click="goToToday" class="mb-1 ml-1">Hoy</flux:button>
                    @endif
                </div>

            </div>
        </div>

        <!-- Totales del día -->
        <div class="mt-7 grid gap-3 sm:grid-cols-2 xl:grid-cols-4" wire:loading.class="opacity-60" wire:target="date,shiftDay,goToToday">
            @php($day = $this->day)
            <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Ventas · {{ $dayLabel }}</p>
                    <flux:icon icon="shopping-cart" class="size-4 text-emerald-500" />
                </div>
                <p class="mt-2 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-white">S/ {{ number_format($day['sales_total'], 2) }}</p>
                <p class="text-xs text-zinc-500">{{ $day['sales_count'] }} {{ $day['sales_count'] === 1 ? 'venta' : 'ventas' }}</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Alquileres · {{ $dayLabel }}</p>
                    <flux:icon icon="sparkles" class="size-4 text-violet-500" />
                </div>
                <p class="mt-2 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-white">S/ {{ number_format($day['rentals_total'], 2) }}</p>
                <p class="text-xs text-zinc-500">{{ $day['rentals_count'] }} {{ $day['rentals_count'] === 1 ? 'alquiler' : 'alquileres' }}</p>
            </div>
            <div class="rounded-2xl border border-violet-200 bg-violet-50/70 p-4 dark:border-violet-900/60 dark:bg-violet-950/30">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wide text-violet-700 dark:text-violet-300">Ingresos · {{ $dayLabel }}</p>
                    <flux:icon icon="banknotes" class="size-4 text-violet-600" />
                </div>
                <p class="mt-2 text-2xl font-semibold tabular-nums text-violet-900 dark:text-white">S/ {{ number_format($day['income'], 2) }}</p>
                <p class="text-xs text-violet-700/80 dark:text-violet-300/80">Ventas + alquileres</p>
            </div>
            @can('dashboard.finanzas')
                <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Margen de ventas</p>
                        <flux:icon icon="arrow-trending-up" class="size-4 text-emerald-500" />
                    </div>
                    <p class="mt-2 text-2xl font-semibold tabular-nums {{ $day['sales_margin'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                        S/ {{ number_format($day['sales_margin'], 2) }}</p>
                    <p class="text-xs text-zinc-500">Sin IGV, sobre el costo de compra</p>
                </div>
            @else
                <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Prendas fuera</p>
                        <flux:icon icon="clock" class="size-4 text-sky-500" />
                    </div>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $this->rentalsOut['out'] }}</p>
                    <p class="text-xs text-zinc-500">Alquileres en curso</p>
                </div>
            @endcan
        </div>
    </div>

    <!-- Alertas -->
    @if ($this->rentalsOut['overdue'] > 0 || $this->lowStock->isNotEmpty())
        <div class="grid gap-3 lg:grid-cols-2">
            @if ($this->rentalsOut['overdue'] > 0)
                <flux:callout variant="danger" icon="exclamation-triangle"
                    heading="{{ $this->rentalsOut['overdue'] }} {{ $this->rentalsOut['overdue'] === 1 ? 'alquiler vencido' : 'alquileres vencidos' }}">
                    <flux:callout.text>Hay prendas que ya debieron devolverse.</flux:callout.text>
                    <x-slot name="actions">
                        <flux:button size="sm" :href="route('rental.rentals.index', ['status' => 'overdue'])" wire:navigate>Ver alquileres</flux:button>
                    </x-slot>
                </flux:callout>
            @endif
            @if ($this->lowStock->isNotEmpty())
                <flux:callout variant="warning" icon="archive-box"
                    heading="{{ $this->lowStock->count() }} {{ $this->lowStock->count() === 1 ? 'variante con stock bajo' : 'variantes con stock bajo' }}">
                    <flux:callout.text>Revisa la lista de alertas de stock más abajo.</flux:callout.text>
                </flux:callout>
            @endif
        </div>
    @endif

    <!-- Mes -->
    <div
        class="rounded-[2rem] border border-white/80 bg-white/70 p-5 shadow-xl shadow-slate-900/[0.04] backdrop-blur-xl sm:p-8 dark:border-slate-800 dark:bg-slate-900/75">
        <div class="flex items-center justify-between">
            <flux:heading size="lg">Mes de {{ $monthLabel }}</flux:heading>
            <flux:icon icon="calendar-days" class="size-5 text-zinc-400" />
        </div>

        @php($month = $this->month)
        <div class="mt-5 grid gap-3 sm:grid-cols-3">
            <div class="rounded-2xl bg-zinc-50 p-4 dark:bg-zinc-800/60">
                <p class="text-xs text-zinc-500">Ventas</p>
                <p class="mt-1 text-xl font-semibold tabular-nums">S/ {{ number_format($month['sales_total'], 2) }}</p>
                <p class="text-xs text-zinc-500">{{ $month['sales_count'] }} operaciones</p>
            </div>
            <div class="rounded-2xl bg-zinc-50 p-4 dark:bg-zinc-800/60">
                <p class="text-xs text-zinc-500">Alquileres</p>
                <p class="mt-1 text-xl font-semibold tabular-nums">S/ {{ number_format($month['rentals_total'], 2) }}</p>
                <p class="text-xs text-zinc-500">{{ $month['rentals_count'] }} operaciones</p>
            </div>
            <div class="rounded-2xl bg-zinc-50 p-4 dark:bg-zinc-800/60">
                <p class="text-xs text-zinc-500">Ingresos</p>
                <p class="mt-1 text-xl font-semibold tabular-nums">S/ {{ number_format($month['income'], 2) }}</p>
                <p class="text-xs text-zinc-500">Ventas + alquileres (con IGV)</p>
            </div>
        </div>

        @can('dashboard.finanzas')
            <!-- Ganancia del mes: desglose -->
            <div class="mt-3 grid gap-3 lg:grid-cols-[1fr_20rem]">
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ([
                        ['Margen de ventas', $month['sales_margin'], '+', 'Venta sin IGV − costo de lo vendido'],
                        ['Alquileres', $month['rentals_total'], '+', 'Alquileres salidos en el mes'],
                        ['Cobros por daños', $month['damage_charges'], '+', 'Cobrado al recepcionar alquileres'],
                        ['Gastos de mantenimiento', $month['maintenance_cost'], '−', 'Lavado, arreglos, planchado'],
                        ['Gastos operativos', $month['expenses'], '−', 'Alquiler del local, servicios...'],
                        ['Pérdidas por bajas', $month['disposal_losses'], '−', 'Costo de prendas perdidas o dadas de baja'],
                    ] as [$label, $amount, $sign, $hint])
                        <div class="rounded-2xl bg-zinc-50 p-4 dark:bg-zinc-800/60">
                            <p class="flex items-center gap-1.5 text-xs text-zinc-500">
                                <span @class([
                                    'grid size-4 place-items-center rounded text-[10px] font-bold',
                                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300' => $sign === '+',
                                    'bg-red-100 text-red-700 dark:bg-red-950/60 dark:text-red-300' => $sign === '−',
                                ])>{{ $sign }}</span>
                                {{ $label }}
                            </p>
                            <p @class([
                                'mt-1 text-xl font-semibold tabular-nums',
                                'text-emerald-700 dark:text-emerald-400' => $sign === '+',
                                'text-red-600 dark:text-red-400' => $sign === '−',
                            ])>S/ {{ number_format($amount, 2) }}</p>
                            <p class="text-xs text-zinc-500">{{ $hint }}</p>
                        </div>
                    @endforeach
                </div>

                <div class="flex flex-col gap-3">
                    <div @class([
                        'flex-1 rounded-2xl p-5',
                        'bg-violet-600 text-white' => $month['profit'] >= 0,
                        'bg-red-600 text-white' => $month['profit'] < 0,
                    ])>
                        <p class="text-xs font-semibold uppercase tracking-wide text-white/80">Ganancia del mes</p>
                        <p class="mt-2 text-3xl font-semibold tabular-nums">S/ {{ number_format($month['profit'], 2) }}</p>
                        <p class="mt-2 text-xs text-white/80">
                            Margen + alquileres + cobros por daños − mantenimiento − gastos operativos − bajas.
                        </p>
                    </div>
                    <div class="rounded-2xl border border-dashed border-zinc-300 p-4 dark:border-zinc-700">
                        <p class="text-xs text-zinc-500">Compras del mes (flujo de caja)</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums">S/ {{ number_format($month['purchases'], 2) }}</p>
                        <p class="text-xs text-zinc-500">
                            No se resta de la ganancia: su costo entra cuando la prenda se vende o se da de baja.
                        </p>
                    </div>
                </div>
            </div>
        @endcan

        <!-- Gráfico anual -->
        @php($series = $this->yearSeries)
        <div wire:key="dashboard-chart-{{ substr($date, 0, 4) }}"
            x-data="dashboardYearChart(@js($months), @js(array_values($series['sales'])), @js(array_values($series['rentals'])), @js(auth()->user()->can('dashboard.finanzas') ? array_values($series['expenses']) : null))"
            class="mt-6 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
            <div class="mb-3 flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Año {{ substr($date, 0, 4) }}: ingresos por mes</p>
                <p class="text-xs text-zinc-500">
                    Total: S/ {{ number_format(array_sum($series['sales']) + array_sum($series['rentals']), 2) }}
                </p>
            </div>
            <div class="h-72">
                <canvas x-ref="canvas"></canvas>
            </div>
        </div>
    </div>

    <!-- Rankings y alertas de stock -->
    <div class="grid gap-5 xl:grid-cols-3">
        @foreach ([['Más vendidos', $this->topSold, 'unid.', 'emerald'], ['Más alquilados', $this->topRented, 'veces', 'violet']] as [$title, $rows, $unit, $tone])
            <div class="rounded-[1.5rem] border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <flux:heading size="lg">{{ $title }}</flux:heading>
                <flux:text class="text-xs">{{ ucfirst($monthLabel) }}</flux:text>

                @php($max = max(1, (int) ($rows->max('units') ?? 0)))
                <div class="mt-4 flex flex-col gap-3">
                    @forelse ($rows as $row)
                        <div wire:key="{{ $tone }}-rank-{{ $loop->index }}">
                            <div class="flex items-baseline justify-between gap-2 text-sm">
                                <span class="truncate font-medium text-zinc-800 dark:text-zinc-200">{{ $row->name }}</span>
                                <span class="shrink-0 tabular-nums text-zinc-500">{{ (int) $row->units }} {{ $unit }}</span>
                            </div>
                            <div class="mt-1 h-1.5 rounded-full bg-zinc-100 dark:bg-zinc-800">
                                <div class="h-1.5 rounded-full {{ $tone === 'emerald' ? 'bg-emerald-500' : 'bg-violet-500' }}"
                                    style="width: {{ round((int) $row->units / $max * 100) }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="py-6 text-center text-sm text-zinc-500">Sin movimientos en {{ $monthLabel }}.</p>
                    @endforelse
                </div>
            </div>
        @endforeach

        <div class="rounded-[1.5rem] border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">Alertas de stock</flux:heading>
            <flux:text class="text-xs">Variantes con 2 o menos unidades disponibles</flux:text>

            <div class="mt-4 flex flex-col divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($this->lowStock as $row)
                    <div class="flex items-center justify-between gap-3 py-2" wire:key="low-stock-{{ $loop->index }}">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $row['product'] }}</p>
                            <p class="text-xs text-zinc-500">{{ $row['variant'] }} · {{ $row['rentable'] ? 'prendas disponibles' : 'stock' }}</p>
                        </div>
                        <flux:badge size="sm" :color="$row['available'] === 0 ? 'red' : 'amber'">{{ $row['available'] }}</flux:badge>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-zinc-500">Todo el inventario tiene stock suficiente.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

@script
<script>
    Alpine.data('dashboardYearChart', (labels, sales, rentals, expenses) => ({
        chart: null,

        init() {
            if (! window.Chart) {
                return;
            }

            const datasets = [
                { label: 'Ventas', data: sales, backgroundColor: 'rgba(16, 185, 129, 0.75)', borderRadius: 6, stack: 'income' },
                { label: 'Alquileres', data: rentals, backgroundColor: 'rgba(124, 58, 237, 0.75)', borderRadius: 6, stack: 'income' },
            ];

            if (expenses) {
                datasets.push({
                    type: 'line', label: 'Gastos', data: expenses, stack: 'expenses',
                    borderColor: 'rgba(239, 68, 68, 0.9)', backgroundColor: 'rgba(239, 68, 68, 0.9)', tension: 0.3,
                });
            }

            const money = (value) => 'S/ ' + Number(value).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            this.chart = new window.Chart(this.$refs.canvas, {
                type: 'bar',
                data: { labels, datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' },
                        tooltip: { callbacks: { label: (ctx) => `${ctx.dataset.label}: ${money(ctx.parsed.y)}` } },
                    },
                    scales: {
                        x: { stacked: true },
                        y: { stacked: true, beginAtZero: true, ticks: { callback: (value) => 'S/ ' + Number(value).toLocaleString('es-PE') } },
                    },
                },
            });
        },

        destroy() {
            this.chart?.destroy();
        },
    }));
</script>
@endscript
