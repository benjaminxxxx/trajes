<div>
    <flux:modal wire:model="isOpen" class="w-full max-w-6xl">
        @if ($isOpen)
            <div class="flex flex-col gap-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div class="mb-2 flex items-center gap-2 text-xs font-semibold text-violet-600 dark:text-violet-400">
                            <span class="grid size-6 place-items-center rounded-lg bg-violet-100 dark:bg-violet-950/60">
                                <flux:icon icon="chart-bar" class="size-3.5 text-violet-600 dark:text-violet-400" />
                            </span>
                            Resumen anual
                        </div>
                        <h2 class="text-2xl font-semibold text-zinc-900 dark:text-white">Gastos de {{ $year }}</h2>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            Total del año: <b class="text-zinc-900 dark:text-white">S/ {{ number_format($this->grandTotal, 2) }}</b>
                        </p>
                    </div>

                    <flux:select wire:model.live="year" class="sm:w-36">
                        @foreach ($this->years as $y)
                            <flux:select.option value="{{ $y }}">{{ $y }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                {{-- Tabla de doble entrada: categorías (filas) × meses (columnas) --}}
                <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
                    <table class="w-full min-w-[64rem] text-xs">
                        <thead class="bg-zinc-50 text-zinc-500 dark:bg-zinc-800/60">
                            <tr>
                                <th class="sticky left-0 z-10 bg-zinc-50 px-3 py-2 text-left font-semibold dark:bg-zinc-800">Gasto</th>
                                @foreach ($months as $label)
                                    <th class="px-2 py-2 text-right font-semibold">{{ $label }}</th>
                                @endforeach
                                <th class="px-3 py-2 text-right font-semibold">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @forelse ($this->matrix as $category => $row)
                                <tr wire:key="summary-row-{{ $loop->index }}">
                                    <td class="sticky left-0 z-10 bg-white px-3 py-2 font-medium text-zinc-800 dark:bg-zinc-900 dark:text-zinc-200">{{ $category }}</td>
                                    @foreach ($row['months'] as $amount)
                                        <td class="px-2 py-2 text-right tabular-nums {{ $amount > 0 ? 'text-zinc-800 dark:text-zinc-200' : 'text-zinc-300 dark:text-zinc-600' }}">
                                            {{ $amount > 0 ? number_format($amount, 2) : '—' }}
                                        </td>
                                    @endforeach
                                    <td class="px-3 py-2 text-right font-semibold tabular-nums text-zinc-900 dark:text-white">{{ number_format($row['total'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="14" class="px-3 py-8 text-center text-sm text-zinc-500">Sin gastos registrados en {{ $year }}.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if ($this->matrix->isNotEmpty())
                            <tfoot class="border-t-2 border-zinc-200 bg-zinc-50 font-semibold dark:border-zinc-700 dark:bg-zinc-800/60">
                                <tr>
                                    <td class="sticky left-0 z-10 bg-zinc-50 px-3 py-2 dark:bg-zinc-800">Total mes</td>
                                    @foreach ($this->monthlyTotals as $total)
                                        <td class="px-2 py-2 text-right tabular-nums">{{ $total > 0 ? number_format($total, 2) : '—' }}</td>
                                    @endforeach
                                    <td class="px-3 py-2 text-right tabular-nums">{{ number_format($this->grandTotal, 2) }}</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>

                {{-- Gráfico mensual. La key por año hace que Livewire recree el bloque (y el gráfico) al cambiar de año. --}}
                @if ($this->matrix->isNotEmpty())
                    @php($chartTotals = array_map(fn ($v) => round($v, 2), array_values($this->monthlyTotals)))
                    <div wire:key="expense-chart-{{ $year }}"
                        x-data="expenseChart(@js($months), @js($chartTotals), {{ $year }})"
                        class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">Gasto por mes</p>
                        <div class="h-72">
                            <canvas x-ref="canvas"></canvas>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </flux:modal>
</div>

@script
<script>
    Alpine.data('expenseChart', (labels, totals, year) => ({
        chart: null,

        init() {
            if (! window.Chart) {
                return;
            }

            this.chart = new window.Chart(this.$refs.canvas, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: `Gastos ${year}`,
                        data: totals,
                        backgroundColor: 'rgba(124, 58, 237, 0.75)',
                        borderRadius: 6,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => 'S/ ' + ctx.parsed.y.toLocaleString('es-PE', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2,
                                }),
                            },
                        },
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { callback: (value) => 'S/ ' + value.toLocaleString('es-PE') },
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