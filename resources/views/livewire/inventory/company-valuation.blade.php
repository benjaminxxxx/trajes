<div
    class="rounded-[2rem] border border-white/80 bg-white/70 p-5 shadow-xl shadow-slate-900/[0.04] backdrop-blur-xl sm:p-8 dark:border-slate-800 dark:bg-slate-900/75">

    <!-- Header -->
    <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div class="space-y-4">
            <flux:breadcrumbs>
                <flux:breadcrumbs.item :href="route('dashboard')" icon="home" />
                <flux:breadcrumbs.item>Inventario</flux:breadcrumbs.item>
                <flux:breadcrumbs.item :href="route('inventory.valuation.index')">Valorizado</flux:breadcrumbs.item>
            </flux:breadcrumbs>

            <div>
                <flux:heading size="xl">Valorizado de la empresa</flux:heading>
                <flux:text class="mt-1.5">
                    Lo que costó y lo que vale hoy todo lo que tiene el negocio.
                    Desgaste de prendas:
                    @forelse ($rules as $rule)
                        desde {{ $rule->min_rentals }} alquileres −{{ rtrim(rtrim(number_format($rule->percent, 2), '0'), '.') }} %{{ $loop->last ? '.' : ',' }}
                    @empty
                        sin reglas configuradas.
                    @endforelse
                </flux:text>
            </div>
        </div>

        <div class="flex gap-2">
            @can('configuracion.general')
                <flux:button :href="route('core.settings.index')" wire:navigate icon="adjustments-horizontal">Desgaste</flux:button>
            @endcan
            @can('inventario.bienes')
                <flux:button :href="route('inventory.assets.index')" wire:navigate icon="cube">Bienes</flux:button>
            @endcan
        </div>
    </div>

    <!-- Resumen -->
    @php($summary = $this->summary)
    <div class="mt-7 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['Prendas de alquiler', $summary['garments'], 'prendas'],
            ['Stock de venta', $summary['stock'], 'unidades'],
            ['Bienes', $summary['assets'], 'unidades'],
        ] as [$label, $block, $unit])
            <div class="rounded-2xl bg-zinc-50 p-4 dark:bg-zinc-800/60">
                <p class="text-xs text-zinc-500">{{ $label }} · {{ $block['count'] }} {{ $unit }}</p>
                <p class="mt-1 text-xl font-semibold tabular-nums">S/ {{ number_format($block['value'], 2) }}</p>
                <p class="text-xs text-zinc-500">Costo de compra: S/ {{ number_format($block['cost'], 2) }}</p>
            </div>
        @endforeach
        <div class="rounded-2xl bg-violet-600 p-4 text-white" data-test="valuation-total">
            <p class="text-xs font-semibold uppercase tracking-wide text-white/80">Total valorizado</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums">S/ {{ number_format($summary['value'], 2) }}</p>
            <p class="text-xs text-white/80">Costo de compra: S/ {{ number_format($summary['cost'], 2) }}</p>
        </div>
    </div>

    <!-- Pestañas -->
    <div class="mt-6 flex flex-col gap-3 lg:flex-row lg:items-center">
        <flux:radio.group wire:model.live="tab" variant="segmented">
            <flux:radio value="prendas">Prendas de alquiler</flux:radio>
            <flux:radio value="stock">Stock de venta</flux:radio>
            <flux:radio value="bienes">Bienes</flux:radio>
        </flux:radio.group>

        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Buscar..." />
    </div>

    <div class="mt-5 overflow-x-auto rounded-2xl border border-zinc-200 dark:border-zinc-800">
        <table class="w-full min-w-[44rem] text-sm">
            <thead class="bg-zinc-50 text-xs text-zinc-500 dark:bg-zinc-800/60">
                <tr>
                    @if ($tab === 'prendas')
                        <th class="px-4 py-3 text-left font-semibold">Prenda</th>
                        <th class="px-4 py-3 text-right font-semibold">Alquileres</th>
                        <th class="px-4 py-3 text-right font-semibold">Costo de compra</th>
                        <th class="px-4 py-3 text-right font-semibold">Desgaste</th>
                        <th class="px-4 py-3 text-right font-semibold">Valor actual</th>
                    @elseif ($tab === 'stock')
                        <th class="px-4 py-3 text-left font-semibold">Producto</th>
                        <th class="px-4 py-3 text-right font-semibold">Stock</th>
                        <th class="px-4 py-3 text-right font-semibold">Costo promedio</th>
                        <th class="px-4 py-3 text-right font-semibold">Valor</th>
                    @else
                        <th class="px-4 py-3 text-left font-semibold">Bien</th>
                        <th class="px-4 py-3 text-right font-semibold">Cantidad</th>
                        <th class="px-4 py-3 text-right font-semibold">Costo de compra</th>
                        <th class="px-4 py-3 text-right font-semibold">Valor actual</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($rows as $row)
                    <tr wire:key="{{ $tab }}-{{ $row->id }}">
                        @if ($tab === 'prendas')
                            <td class="px-4 py-3">
                                <p class="font-medium text-zinc-900 dark:text-white">{{ $row->variant?->product?->name }}</p>
                                <p class="text-xs text-zinc-500">{{ $row->code }} · {{ $row->variant?->label() }}</p>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $row->rentals_count }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-zinc-500">S/ {{ number_format($row->purchase_cost, 2) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                @if ($row->depreciation_percent > 0)
                                    <span class="text-red-600 dark:text-red-400">−{{ rtrim(rtrim(number_format($row->depreciation_percent, 2), '0'), '.') }} %</span>
                                @else
                                    <span class="text-zinc-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-medium tabular-nums">S/ {{ number_format($row->current_value, 2) }}</td>
                        @elseif ($tab === 'stock')
                            <td class="px-4 py-3">
                                <p class="font-medium text-zinc-900 dark:text-white">{{ $row->product?->name }}</p>
                                <p class="text-xs text-zinc-500">{{ $row->product?->code }} · {{ $row->label() }}</p>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $row->stock }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-zinc-500">S/ {{ number_format($row->average_cost, 2) }}</td>
                            <td class="px-4 py-3 text-right font-medium tabular-nums">S/ {{ number_format($row->stock * (float) $row->average_cost, 2) }}</td>
                        @else
                            <td class="px-4 py-3">
                                <p class="font-medium text-zinc-900 dark:text-white">{{ $row->name }}</p>
                                <p class="text-xs text-zinc-500">{{ $assetTypes[$row->type] ?? $row->type }}</p>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $row->quantity }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-zinc-500">S/ {{ number_format($row->totalCost(), 2) }}</td>
                            <td class="px-4 py-3 text-right font-medium tabular-nums">S/ {{ number_format($row->totalValue(), 2) }}</td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-12 text-center text-zinc-500">Nada que mostrar.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $rows->links() }}
    </div>
</div>
