<div>
    <flux:modal wire:model="isOpen" class="w-full max-w-6xl">
        @if ($isOpen)
            @php
                $isAmount = $metric === 'amount';
                $format = fn ($value) => $isAmount ? number_format($value, 2) : number_format($value);
                $grandTotal = array_sum($this->monthlyTotals);
            @endphp

            <div class="flex flex-col gap-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div class="mb-2 flex items-center gap-2 text-xs font-semibold text-violet-600 dark:text-violet-400">
                            <span class="grid size-6 place-items-center rounded-lg bg-violet-100 dark:bg-violet-950/60">
                                <flux:icon icon="chart-bar" class="size-3.5 text-violet-600 dark:text-violet-400" />
                            </span>
                            Resumen anual
                        </div>
                        <h2 class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ $label }} de {{ $year }}</h2>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            Total del año:
                            <b class="text-zinc-900 dark:text-white">{{ $isAmount ? 'S/ ' : '' }}{{ $format($grandTotal) }}{{ $isAmount ? '' : ' unid.' }}</b>
                            · No incluye operaciones anuladas.
                        </p>
                    </div>

                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <flux:radio.group wire:model.live="metric" variant="segmented" size="sm">
                            <flux:radio value="units">Unidades</flux:radio>
                            <flux:radio value="amount">Importe</flux:radio>
                        </flux:radio.group>

                        <flux:select wire:model.live="year" class="sm:w-32">
                            @foreach ($this->years as $y)
                                <flux:select.option value="{{ $y }}">{{ $y }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                </div>

                {{-- Tabla de doble entrada: categorías (filas) × meses (columnas) --}}
                <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
                    <table class="w-full min-w-[64rem] text-xs">
                        <thead class="bg-zinc-50 text-zinc-500 dark:bg-zinc-800/60">
                            <tr>
                                <th class="sticky left-0 z-10 bg-zinc-50 px-3 py-2 text-left font-semibold dark:bg-zinc-800">Categoría</th>
                                @foreach ($months as $monthLabel)
                                    <th class="px-2 py-2 text-right font-semibold">{{ $monthLabel }}</th>
                                @endforeach
                                <th class="px-3 py-2 text-right font-semibold">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @forelse ($this->matrix as $category => $row)
                                <tr wire:key="summary-row-{{ $loop->index }}">
                                    <td class="sticky left-0 z-10 bg-white px-3 py-2 font-medium text-zinc-800 dark:bg-zinc-900 dark:text-zinc-200">{{ $category }}</td>
                                    @foreach ($row['months'] as $value)
                                        <td class="px-2 py-2 text-right tabular-nums {{ $value > 0 ? 'text-zinc-800 dark:text-zinc-200' : 'text-zinc-300 dark:text-zinc-600' }}">
                                            {{ $value > 0 ? $format($value) : '—' }}
                                        </td>
                                    @endforeach
                                    <td class="px-3 py-2 text-right font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $format($row['total']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="14" class="px-3 py-8 text-center text-sm text-zinc-500">Sin {{ strtolower($label) }} registrados en {{ $year }}.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if (! empty($this->matrix))
                            <tfoot class="border-t-2 border-zinc-200 bg-zinc-50 font-semibold dark:border-zinc-700 dark:bg-zinc-800/60">
                                <tr>
                                    <td class="sticky left-0 z-10 bg-zinc-50 px-3 py-2 dark:bg-zinc-800">Total mes</td>
                                    @foreach ($this->monthlyTotals as $total)
                                        <td class="px-2 py-2 text-right tabular-nums">{{ $total > 0 ? $format($total) : '—' }}</td>
                                    @endforeach
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $format($grandTotal) }}</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>

                {{-- Gráfico mensual. La key hace que Livewire recree el gráfico al cambiar de año o métrica. --}}
                @if (! empty($this->matrix))
                    @php($chartTotals = array_map(fn ($v) => round($v, 2), array_values($this->monthlyTotals)))
                    <div wire:key="summary-chart-{{ $year }}-{{ $metric }}"
                        x-data="categorySummaryChart(@js($months), @js($chartTotals), @js($label . ' ' . $year), @js($isAmount))"
                        class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ $label }} por mes</p>
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
    Alpine.data('categorySummaryChart', (labels, totals, title, isAmount) => ({
        chart: null,

        init() {
            if (! window.Chart) {
                return;
            }

            const format = (value) => isAmount
                ? 'S/ ' + value.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                : value.toLocaleString('es-PE') + ' unid.';

            this.chart = new window.Chart(this.$refs.canvas, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: title,
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
                        tooltip: { callbacks: { label: (ctx) => format(ctx.parsed.y) } },
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0, callback: (value) => isAmount ? 'S/ ' + value.toLocaleString('es-PE') : value },
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
