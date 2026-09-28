<div>
    <flux:modal wire:model="isOpen" class="w-full max-w-6xl">
        @if ($isOpen)
            @php
                $rentalsTotal = array_sum($this->matrix['rentals']);
                $salesTotal = array_sum($this->matrix['sales']);
                $rows = [
                    'Alquileres' => $this->matrix['rentals'],
                    'Ventas' => $this->matrix['sales'],
                ];
            @endphp

            <div class="flex flex-col gap-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div class="mb-2 flex items-center gap-2 text-xs font-semibold text-violet-600 dark:text-violet-400">
                            <span class="grid size-6 place-items-center rounded-lg bg-violet-100 dark:bg-violet-950/60">
                                <flux:icon icon="chart-bar" class="size-3.5 text-violet-600 dark:text-violet-400" />
                            </span>
                            Reporte anual
                        </div>
                        <h2 class="text-2xl font-semibold text-zinc-900 dark:text-white">
                            Movimiento de {{ $year }}
                        </h2>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            <b class="text-zinc-900 dark:text-white">{{ $rentalsTotal }}</b> unidades alquiladas y
                            <b class="text-zinc-900 dark:text-white">{{ $salesTotal }}</b> vendidas
                        </p>
                    </div>

                    <div class="flex flex-col gap-2 sm:flex-row">
                        <flux:select wire:model.live="categoryId" class="sm:w-56">
                            <flux:select.option value="">Todas las categorías</flux:select.option>
                            @foreach ($this->categoryOptions as $option)
                                <flux:select.option value="{{ $option->id }}">{{ $option->name }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:select wire:model.live="year" class="sm:w-32">
                            @foreach ($this->years as $y)
                                <flux:select.option value="{{ $y }}">{{ $y }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                </div>

                {{-- Tabla de doble entrada: tipo de movimiento (filas) × meses (columnas) --}}
                <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
                    <table class="w-full min-w-[64rem] text-xs">
                        <thead class="bg-zinc-50 text-zinc-500 dark:bg-zinc-800/60">
                            <tr>
                                <th class="sticky left-0 z-10 bg-zinc-50 px-3 py-2 text-left font-semibold dark:bg-zinc-800">Unidades</th>
                                @foreach ($months as $label)
                                    <th class="px-2 py-2 text-right font-semibold">{{ $label }}</th>
                                @endforeach
                                <th class="px-3 py-2 text-right font-semibold">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($rows as $label => $values)
                                <tr wire:key="report-row-{{ $loop->index }}">
                                    <td class="sticky left-0 z-10 bg-white px-3 py-2 font-medium text-zinc-800 dark:bg-zinc-900 dark:text-zinc-200">
                                        <span class="inline-flex items-center gap-2">
                                            <span @class(['size-2 rounded-full', 'bg-violet-600' => $loop->first, 'bg-emerald-500' => $loop->last])></span>
                                            {{ $label }}
                                        </span>
                                    </td>
                                    @foreach ($values as $units)
                                        <td class="px-2 py-2 text-right tabular-nums {{ $units > 0 ? 'text-zinc-800 dark:text-zinc-200' : 'text-zinc-300 dark:text-zinc-600' }}">
                                            {{ $units > 0 ? $units : '—' }}
                                        </td>
                                    @endforeach
                                    <td class="px-3 py-2 text-right font-semibold tabular-nums text-zinc-900 dark:text-white">{{ array_sum($values) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="grid gap-4 lg:grid-cols-5">
                    {{-- Gráfico mensual. La key hace que Livewire recree el gráfico al cambiar de año o categoría. --}}
                    <div wire:key="category-chart-{{ $year }}-{{ $categoryId ?? 'all' }}"
                        x-data="categoryMonthlyChart(@js($months), @js(array_values($this->matrix['rentals'])), @js(array_values($this->matrix['sales'])))"
                        class="rounded-xl border border-zinc-200 p-4 lg:col-span-3 dark:border-zinc-800">
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">Alquileres vs ventas por mes</p>
                        <div class="h-72">
                            <canvas x-ref="canvas"></canvas>
                        </div>
                    </div>

                    {{-- Total de productos por categoría --}}
                    <div class="rounded-xl border border-zinc-200 p-4 lg:col-span-2 dark:border-zinc-800">
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">Productos por categoría</p>
                        <div class="max-h-72 overflow-y-auto">
                            <table class="w-full text-xs">
                                <thead class="text-zinc-500">
                                    <tr>
                                        <th class="py-1.5 text-left font-semibold">Categoría</th>
                                        <th class="py-1.5 text-right font-semibold">Productos</th>
                                        <th class="py-1.5 text-right font-semibold">Alq.</th>
                                        <th class="py-1.5 text-right font-semibold">Vend.</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                    @php($maxProducts = max(1, $this->categorySummary->max('products') ?? 0))
                                    @foreach ($this->categorySummary as $row)
                                        <tr wire:key="category-summary-{{ $loop->index }}">
                                            <td class="py-2 pr-2">
                                                <p class="font-medium text-zinc-800 dark:text-zinc-200">{{ $row['name'] }}</p>
                                                <div class="mt-1 h-1 rounded-full bg-zinc-100 dark:bg-zinc-800">
                                                    <div class="h-1 rounded-full bg-violet-500"
                                                        style="width: {{ round($row['products'] / $maxProducts * 100) }}%"></div>
                                                </div>
                                            </td>
                                            <td class="py-2 text-right font-semibold tabular-nums">{{ $row['products'] }}</td>
                                            <td class="py-2 text-right tabular-nums text-zinc-500">{{ $row['rentals'] }}</td>
                                            <td class="py-2 text-right tabular-nums text-zinc-500">{{ $row['sales'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="border-t-2 border-zinc-200 font-semibold dark:border-zinc-700">
                                    <tr>
                                        <td class="py-2">Total</td>
                                        <td class="py-2 text-right tabular-nums">{{ $this->categorySummary->sum('products') }}</td>
                                        <td class="py-2 text-right tabular-nums">{{ $this->categorySummary->sum('rentals') }}</td>
                                        <td class="py-2 text-right tabular-nums">{{ $this->categorySummary->sum('sales') }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </flux:modal>
</div>

@script
<script>
    Alpine.data('categoryMonthlyChart', (labels, rentals, sales) => ({
        chart: null,

        init() {
            if (! window.Chart) {
                return;
            }

            this.chart = new window.Chart(this.$refs.canvas, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [
                        {
                            label: 'Alquileres',
                            data: rentals,
                            backgroundColor: 'rgba(124, 58, 237, 0.75)',
                            borderRadius: 6,
                        },
                        {
                            label: 'Ventas',
                            data: sales,
                            backgroundColor: 'rgba(16, 185, 129, 0.75)',
                            borderRadius: 6,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => `${ctx.dataset.label}: ${ctx.parsed.y} unid.`,
                            },
                        },
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0 },
                        },
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
