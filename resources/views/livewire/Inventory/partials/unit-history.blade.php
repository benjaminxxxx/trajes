@php
    $unit = $history['unit'];
    $variant = $unit->variant;
    $purchase = $history['purchase'];
    $sale = $history['sale'];
@endphp

<div class="flex flex-col gap-5">
    <!-- Ficha -->
    <div class="rounded-[1.5rem] border border-zinc-200 bg-white p-5 sm:p-6 dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-violet-500">
                    {{ $variant?->product?->category?->name ?? 'Sin categoría' }} · Prenda física
                </p>
                <h2 class="mt-1 font-mono text-2xl font-semibold text-zinc-900 dark:text-white">{{ $unit->code }}</h2>
                <p class="mt-1 text-sm text-zinc-500">
                    <button type="button" wire:click="selectProduct({{ $variant?->product_id }})" class="font-medium text-violet-600 hover:underline dark:text-violet-400">
                        {{ $variant?->product?->name }}
                    </button>
                    · {{ $variant?->label() }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <flux:badge :color="\App\Models\PhysicalProduct::STATUS_COLORS[$unit->status] ?? 'zinc'">
                    {{ \App\Models\PhysicalProduct::STATUS_LABELS[$unit->status] ?? $unit->status }}
                </flux:badge>
                @can('mantenimiento.registrar')
                    <flux:button size="sm" icon="wrench-screwdriver" :href="route('maintenance.history.index', ['prenda' => $unit->id])" wire:navigate>
                        Registrar mantenimiento
                    </flux:button>
                @endcan
                @if ($unit->status === 'maintenance' && auth()->user()->can('mantenimiento.registrar'))
                    <flux:button size="sm" icon="check" wire:click="markUnitAvailable"
                        wire:confirm="¿La prenda ya está lista para alquilarse o venderse?">Marcar disponible</flux:button>
                @endif
                <flux:button size="sm" variant="subtle" icon="x-mark" wire:click="clearSelection">Cerrar</flux:button>
            </div>
        </div>

        <!-- KPIs -->
        <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div class="rounded-2xl bg-zinc-50 p-4 dark:bg-zinc-800/60">
                <p class="text-xs text-zinc-500">Costo de compra</p>
                <p class="mt-1 text-xl font-semibold tabular-nums">S/ {{ number_format($history['cost'], 2) }}</p>
                <p class="text-xs text-zinc-500">{{ $purchase ? 'Compra #' . $purchase->id : 'Sin compra registrada' }}</p>
            </div>
            <div class="rounded-2xl bg-zinc-50 p-4 dark:bg-zinc-800/60">
                <p class="text-xs text-zinc-500">{{ $sale || $history['is_disposed'] ? 'Estuvo en tienda' : 'Tiempo en tienda' }}</p>
                <p class="mt-1 text-xl font-semibold tabular-nums">{{ $history['days_in_store'] }} días</p>
                <p class="text-xs text-zinc-500">Desde {{ $history['since']->format('d/m/Y') }}</p>
            </div>
            <div class="rounded-2xl bg-zinc-50 p-4 dark:bg-zinc-800/60">
                <p class="text-xs text-zinc-500">Alquileres</p>
                <p class="mt-1 text-xl font-semibold tabular-nums">{{ $history['rentals_count'] }}</p>
                <p class="text-xs text-zinc-500">S/ {{ number_format($history['rental_income'], 2) }} generados</p>
                @if ($history['damage_income'] > 0)
                    <p class="text-xs text-amber-600 dark:text-amber-400">+ S/ {{ number_format($history['damage_income'], 2) }} por daños</p>
                @endif
            </div>
            <div class="rounded-2xl bg-zinc-50 p-4 dark:bg-zinc-800/60">
                <p class="text-xs text-zinc-500">Mantenimientos</p>
                <p class="mt-1 text-xl font-semibold tabular-nums">{{ $history['maintenances']->count() }}</p>
                <p class="text-xs text-zinc-500">S/ {{ number_format($history['maintenance_cost'], 2) }} gastados</p>
            </div>
            <div @class([
                'rounded-2xl p-4',
                'bg-emerald-50 dark:bg-emerald-950/30' => $history['profit'] >= 0,
                'bg-red-50 dark:bg-red-950/30' => $history['profit'] < 0,
            ])>
                <p class="text-xs text-zinc-500">Resultado de la prenda</p>
                <p @class([
                    'mt-1 text-xl font-semibold tabular-nums',
                    'text-emerald-700 dark:text-emerald-300' => $history['profit'] >= 0,
                    'text-red-600 dark:text-red-400' => $history['profit'] < 0,
                ])>S/ {{ number_format($history['profit'], 2) }}</p>
                <p class="text-xs text-zinc-500">Alquileres + daños + venta − costo − mant.</p>
            </div>
        </div>
    </div>

    <div class="grid gap-5 lg:grid-cols-3">
        <!-- Línea de vida -->
        <div class="rounded-[1.5rem] border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">Línea de vida</flux:heading>

            <ol class="mt-4 space-y-4 border-l border-zinc-200 pl-4 dark:border-zinc-700">
                <li>
                    <p class="text-xs text-zinc-500">{{ $history['since']->format('d/m/Y') }}</p>
                    <p class="text-sm font-medium text-zinc-800 dark:text-zinc-200">Ingresó por compra</p>
                    <p class="text-xs text-zinc-500">
                        {{ $purchase?->supplier?->display_name ?? 'Sin proveedor' }}
                        @if ($purchase?->document_number) · {{ $purchase->document_number }} @endif
                    </p>
                </li>
                @if ($history['rentals_count'] > 0)
                    <li>
                        <p class="text-xs text-zinc-500">
                            {{ $history['rentals']->last()?->rental?->pickup_date?->format('d/m/Y') }} – {{ $history['rentals']->first()?->rental?->pickup_date?->format('d/m/Y') }}
                        </p>
                        <p class="text-sm font-medium text-zinc-800 dark:text-zinc-200">Alquilada {{ $history['rentals_count'] }} {{ $history['rentals_count'] === 1 ? 'vez' : 'veces' }}</p>
                    </li>
                @endif
                @if ($sale)
                    <li>
                        <p class="text-xs text-zinc-500">{{ $sale->sale->sale_date?->format('d/m/Y') }}</p>
                        <p class="text-sm font-medium text-zinc-800 dark:text-zinc-200">Vendida en {{ $sale->sale->code }}</p>
                        <p class="text-xs text-zinc-500">
                            S/ {{ number_format($sale->subtotal * 1.18, 2) }} con IGV ({{ number_format($sale->subtotal, 2) }} sin IGV)
                            · {{ $sale->sale->client?->display_name ?? 'Cliente varios' }}
                        </p>
                    </li>
                @elseif ($history['is_disposed'])
                    <li>
                        <p class="text-xs text-zinc-500">{{ $history['disposed_at']->format('d/m/Y') }}</p>
                        <p class="text-sm font-medium text-red-600 dark:text-red-400">{{ \App\Models\PhysicalProduct::STATUS_LABELS[$unit->status] }}</p>
                        @if ($unit->status_note)
                            <p class="text-xs text-zinc-500">{{ $unit->status_note }}</p>
                        @endif
                    </li>
                @else
                    <li>
                        <p class="text-xs text-zinc-500">Hoy</p>
                        <p class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ \App\Models\PhysicalProduct::STATUS_LABELS[$unit->status] ?? $unit->status }}</p>
                    </li>
                @endif
            </ol>
        </div>

        <!-- Historial de alquileres -->
        <div class="rounded-[1.5rem] border border-zinc-200 bg-white p-5 lg:col-span-2 dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">Alquileres</flux:heading>

            <div class="mt-4 overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
                <table class="w-full min-w-[44rem] text-sm">
                    <thead class="bg-zinc-50 text-xs text-zinc-500 dark:bg-zinc-800/60">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold">Alquiler</th>
                            <th class="px-3 py-2 text-left font-semibold">Cliente</th>
                            <th class="px-3 py-2 text-left font-semibold">Salida</th>
                            <th class="px-3 py-2 text-left font-semibold">Devolución</th>
                            <th class="px-3 py-2 text-left font-semibold">Condición</th>
                            <th class="px-3 py-2 text-right font-semibold">Precio</th>
                            <th class="px-3 py-2 text-right font-semibold">Cobro daño</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse ($history['rentals'] as $detail)
                            @php($rental = $detail->rental)
                            <tr wire:key="unit-rental-{{ $detail->id }}" @class(['opacity-50' => $rental?->isCancelled()])>
                                <td class="px-3 py-2">
                                    <span class="font-medium">{{ $rental?->code }}</span>
                                    @if ($rental?->isCancelled())
                                        <flux:badge size="sm" color="red" class="ml-1">Anulado</flux:badge>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-zinc-600 dark:text-zinc-300">{{ $rental?->client?->display_name }}</td>
                                <td class="px-3 py-2 text-zinc-600 dark:text-zinc-300">{{ $rental?->pickup_date?->format('d/m/Y') }}</td>
                                <td class="px-3 py-2 text-zinc-600 dark:text-zinc-300">
                                    {{ ($rental?->actual_return_date ?? $rental?->expected_return_date)?->format('d/m/Y') }}
                                    @unless ($rental?->actual_return_date)
                                        <span class="text-xs text-zinc-400">(prevista)</span>
                                    @endunless
                                </td>
                                <td class="px-3 py-2 text-xs text-zinc-600 dark:text-zinc-300">
                                    {{ \App\Models\RentalDetail::CONDITION_LABELS[$detail->return_condition] ?? '—' }}
                                    @if ($detail->damage_note)
                                        <span class="block text-zinc-400">{{ $detail->damage_note }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">S/ {{ number_format($detail->price, 2) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums {{ (float) $detail->damage_fee > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-zinc-400' }}">
                                    {{ (float) $detail->damage_fee > 0 ? 'S/ ' . number_format($detail->damage_fee, 2) : '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-3 py-8 text-center text-zinc-500">Esta prenda aún no se ha alquilado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($history['maintenances']->isNotEmpty())
                <flux:heading size="lg" class="mt-6">Mantenimientos</flux:heading>
                <div class="mt-3 flex flex-col divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($history['maintenances'] as $maintenance)
                        <div class="flex items-center justify-between py-2 text-sm">
                            <span>
                                {{ ($maintenance->maintenance_date ?? $maintenance->created_at)?->format('d/m/Y') }} ·
                                {{ \App\Models\ItemMaintenance::TYPES[$maintenance->type] ?? $maintenance->type }}
                                @if ($maintenance->provider_name) · {{ $maintenance->provider_name }} @endif
                            </span>
                            <span class="tabular-nums">S/ {{ number_format($maintenance->cost, 2) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
