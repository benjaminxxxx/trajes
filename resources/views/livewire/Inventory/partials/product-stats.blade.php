@php($stats = $data['stats'])

<div class="flex flex-col gap-5">
    <!-- Encabezado y año -->
    <div class="rounded-[1.5rem] border border-zinc-200 bg-white p-5 sm:p-6 dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-violet-500">
                    {{ $product->category?->name ?? 'Sin categoría' }} · {{ $product->code }}
                </p>
                <h2 class="mt-1 text-2xl font-semibold text-zinc-900 dark:text-white">{{ $product->name }}</h2>
                <p class="mt-1 text-sm text-zinc-500">
                    {{ $product->allow_rental ? 'Producto con prendas físicas (alquiler y venta).' : 'Producto solo de venta, sin registro por prenda.' }}
                </p>
            </div>
            <div class="flex items-end gap-2">
                <flux:select wire:model.live="year" label="Año" class="w-32">
                    @foreach ($data['years'] as $y)
                        <flux:select.option value="{{ $y }}">{{ $y }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:button variant="subtle" icon="x-mark" wire:click="clearSelection">Cerrar</flux:button>
            </div>
        </div>

        <!-- KPIs del año -->
        <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl bg-zinc-50 p-4 dark:bg-zinc-800/60">
                <p class="text-xs text-zinc-500">Unidades vendidas</p>
                <p class="mt-1 text-xl font-semibold tabular-nums">{{ $stats['units_sold'] }}</p>
                <p class="text-xs text-zinc-500">S/ {{ number_format($stats['sales_amount'], 2) }} sin IGV</p>
            </div>
            <div class="rounded-2xl bg-zinc-50 p-4 dark:bg-zinc-800/60">
                <p class="text-xs text-zinc-500">Margen de ventas</p>
                <p class="mt-1 text-xl font-semibold tabular-nums {{ $stats['sales_margin'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600' }}">
                    S/ {{ number_format($stats['sales_margin'], 2) }}</p>
                <p class="text-xs text-zinc-500">Sobre el costo de compra</p>
            </div>
            @if ($product->allow_rental)
                <div class="rounded-2xl bg-zinc-50 p-4 dark:bg-zinc-800/60">
                    <p class="text-xs text-zinc-500">Veces alquilado</p>
                    <p class="mt-1 text-xl font-semibold tabular-nums">{{ $stats['times_rented'] }}</p>
                    <p class="text-xs text-zinc-500">S/ {{ number_format($stats['rental_amount'], 2) }} generados</p>
                </div>
            @endif
            <div class="rounded-2xl bg-zinc-50 p-4 dark:bg-zinc-800/60">
                <p class="text-xs text-zinc-500">Stock actual</p>
                <p class="mt-1 text-xl font-semibold tabular-nums">
                    {{ $product->allow_rental ? $data['stock']->sum('available_units') : $data['stock']->sum('stock') }}
                </p>
                <p class="text-xs text-zinc-500">{{ $product->allow_rental ? 'prendas disponibles' : 'unidades' }}</p>
            </div>
        </div>

        <!-- Por mes -->
        <div wire:key="tracker-chart-{{ $product->id }}-{{ $data['year'] }}"
            x-data="trackerMonthlyChart(@js($months), @js(array_values($stats['months']['sold'])), @js($product->allow_rental ? array_values($stats['months']['rented']) : null))"
            class="mt-6 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
            <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">Movimiento por mes · {{ $data['year'] }}</p>
            <div class="h-64">
                <canvas x-ref="canvas"></canvas>
            </div>
        </div>
    </div>

    <!-- Tallas y colores -->
    <div class="grid gap-5 lg:grid-cols-2">
        @foreach ([['Tallas que más se mueven', $stats['by_size']], ['Colores que más se mueven', $stats['by_color']]] as [$title, $groups])
            <div class="rounded-[1.5rem] border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <flux:heading size="lg">{{ $title }}</flux:heading>
                <flux:text class="text-xs">{{ $data['year'] }}{{ $product->allow_rental ? ' · vendidas y alquiladas' : ' · unidades vendidas' }}</flux:text>

                @php($max = max(1, (int) $groups->max('total')))
                <div class="mt-4 flex flex-col gap-3">
                    @forelse ($groups as $group)
                        <div wire:key="group-{{ $loop->parent->index }}-{{ $loop->index }}">
                            <div class="flex items-baseline justify-between text-sm">
                                <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $group['name'] }}</span>
                                <span class="tabular-nums text-zinc-500">
                                    {{ $group['sold'] }} vend.@if ($product->allow_rental) · {{ $group['rented'] }} alq.@endif
                                </span>
                            </div>
                            <div class="mt-1 flex h-1.5 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                <div class="h-1.5 bg-emerald-500" style="width: {{ round($group['sold'] / $max * 100) }}%"></div>
                                <div class="h-1.5 bg-violet-500" style="width: {{ round($group['rented'] / $max * 100) }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="py-6 text-center text-sm text-zinc-500">Sin movimientos en {{ $data['year'] }}.</p>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>

    <div class="grid gap-5 {{ $product->allow_rental ? 'lg:grid-cols-2' : '' }}">
        <!-- Stock por variante -->
        <div class="rounded-[1.5rem] border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">Stock por talla y color</flux:heading>
            <div class="mt-4 flex flex-col divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($data['stock'] as $row)
                    <div class="flex items-center justify-between py-2 text-sm">
                        <span class="flex items-center gap-2">
                            @if ($row['hex'])
                                <span class="size-3 rounded-full border border-zinc-300" style="background-color: {{ $row['hex'] }}"></span>
                            @endif
                            {{ $row['label'] }}
                        </span>
                        @php($qty = $product->allow_rental ? $row['available_units'] : $row['stock'])
                        <flux:badge size="sm" :color="$qty > 2 ? 'emerald' : ($qty > 0 ? 'amber' : 'red')">
                            {{ $qty }} {{ $product->allow_rental ? 'disp.' : 'en stock' }}
                        </flux:badge>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-zinc-500">Sin variantes registradas.</p>
                @endforelse
            </div>
        </div>

        <!-- Prendas físicas -->
        @if ($product->allow_rental)
            <div class="rounded-[1.5rem] border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <flux:heading size="lg">Prendas físicas</flux:heading>
                <flux:text class="text-xs">Toca una prenda para ver su historia.</flux:text>
                <div class="mt-4 max-h-96 overflow-y-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 bg-zinc-50 text-xs text-zinc-500 dark:bg-zinc-800">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold">Código</th>
                                <th class="px-3 py-2 text-left font-semibold">Talla / color</th>
                                <th class="px-3 py-2 text-right font-semibold">Alq.</th>
                                <th class="px-3 py-2 text-left font-semibold">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($data['units'] as $unit)
                                <tr wire:key="product-unit-{{ $unit->id }}" wire:click="selectUnit({{ $unit->id }})"
                                    class="cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800/40">
                                    <td class="px-3 py-2 font-mono text-xs font-semibold">{{ $unit->code }}</td>
                                    <td class="px-3 py-2 text-xs text-zinc-600 dark:text-zinc-300">{{ $unit->variant?->label() }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $unit->rentals_count }}</td>
                                    <td class="px-3 py-2">
                                        <flux:badge size="sm" :color="\App\Models\PhysicalProduct::STATUS_COLORS[$unit->status] ?? 'zinc'">
                                            {{ \App\Models\PhysicalProduct::STATUS_LABELS[$unit->status] ?? $unit->status }}
                                        </flux:badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
