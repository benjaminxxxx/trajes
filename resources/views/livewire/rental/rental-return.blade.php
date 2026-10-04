<div
    class="rounded-[2rem] border border-white/80 bg-white/70 p-5 shadow-xl shadow-slate-900/[0.04] backdrop-blur-xl sm:p-8 dark:border-slate-800 dark:bg-slate-900/75">

    <!-- Header -->
    <div class="space-y-4">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('dashboard')" icon="home" />
            <flux:breadcrumbs.item :href="route('rental.rentals.index')">Alquileres</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Recepción {{ $rental->code }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <flux:heading size="xl">Recepcionar alquiler {{ $rental->code }}</flux:heading>
                <flux:text class="mt-1.5">
                    {{ $rental->client?->display_name }} · salió el {{ $rental->pickup_date?->format('d/m/Y H:i') }},
                    devolución prevista {{ $rental->expected_return_date?->format('d/m/Y H:i') }}
                </flux:text>
            </div>
            @include('livewire.rental.partials.status-badge', ['rental' => $rental])
        </div>
    </div>

    @if ($completed)
        <!-- Resumen de la recepción -->
        <div class="mt-7 flex flex-col gap-5">
            <flux:callout variant="success" icon="check-circle"
                heading="Alquiler recepcionado el {{ $rental->actual_return_date?->format('d/m/Y H:i') }}">
                <flux:callout.text>
                    @if ((float) $rental->penalty_total > 0)
                        Se registró un cobro por daños de <b>S/ {{ number_format($rental->penalty_total, 2) }}</b> como ingreso.
                    @else
                        Sin cobros por daños.
                    @endif
                    @if ((float) $rental->deposit_amount > 0)
                        Recuerda devolver la garantía de S/ {{ number_format($rental->deposit_amount, 2) }} al cliente: al recepcionar, sale de caja.
                    @endif
                </flux:callout.text>
            </flux:callout>

            @if ($rental->return_notes)
                <div class="rounded-2xl bg-zinc-50 p-4 text-sm dark:bg-zinc-800/60">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-400">Observaciones</p>
                    <p class="mt-1 text-zinc-700 dark:text-zinc-300">{{ $rental->return_notes }}</p>
                </div>
            @endif

            <div class="overflow-x-auto rounded-2xl border border-zinc-200 dark:border-zinc-800">
                <table class="w-full min-w-[40rem] text-sm">
                    <thead class="bg-zinc-50 text-xs text-zinc-500 dark:bg-zinc-800/60">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Prenda</th>
                            <th class="px-4 py-3 text-left font-semibold">Condición</th>
                            <th class="px-4 py-3 text-right font-semibold">Cobro daño</th>
                            <th class="px-4 py-3 text-left font-semibold">Estado actual</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->details as $detail)
                            @php($unit = $detail->physicalProduct)
                            <tr wire:key="done-{{ $detail->id }}">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-zinc-800 dark:text-zinc-200">{{ $unit?->variant?->product?->name }}</p>
                                    <p class="font-mono text-xs text-zinc-500">{{ $unit?->code }} · {{ $unit?->variant?->label() }}</p>
                                </td>
                                <td class="px-4 py-3">{{ $conditions[$detail->return_condition] ?? '—' }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ (float) $detail->damage_fee > 0 ? 'S/ ' . number_format($detail->damage_fee, 2) : '—' }}</td>
                                <td class="px-4 py-3">
                                    <flux:badge size="sm" :color="\App\Models\PhysicalProduct::STATUS_COLORS[$unit?->status] ?? 'zinc'">
                                        {{ \App\Models\PhysicalProduct::STATUS_LABELS[$unit?->status] ?? $unit?->status }}
                                    </flux:badge>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    {{-- Si el cliente decide comprar la prenda, se vende desde Ventas ya preseleccionada --}}
                                    @if ($unit?->status === 'available')
                                        <flux:button size="sm" icon="shopping-cart" :href="route('sale.sales.create', ['prenda' => $unit->id])" wire:navigate>
                                            Vender
                                        </flux:button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex justify-end">
                <flux:button :href="route('rental.rentals.index')" wire:navigate variant="primary">Volver a alquileres</flux:button>
            </div>
        </div>
    @elseif ($rental->status !== \App\Models\Rental::STATUS_OUT)
        <flux:callout class="mt-7" variant="warning" icon="exclamation-triangle" heading="Este alquiler no está en curso">
            <flux:callout.text>Solo se pueden recepcionar alquileres en curso.</flux:callout.text>
        </flux:callout>
    @else
        <!-- Formulario de recepción -->
        <form wire:submit="save" class="mt-7 flex flex-col gap-6">
            <div class="grid gap-4 sm:grid-cols-[16rem_1fr]">
                <flux:input wire:model="actual_return_date" type="datetime-local" label="Fecha y hora de recepción" required />

                @if ((float) $rental->deposit_amount > 0)
                    <flux:callout icon="banknotes" class="self-end">
                        <flux:callout.text>
                            Devuelve la garantía de <b>S/ {{ number_format($rental->deposit_amount, 2) }}</b> al cliente.
                        </flux:callout.text>
                    </flux:callout>
                @endif
            </div>

            @error('items')
                <flux:callout variant="danger" icon="exclamation-triangle" heading="{{ $message }}" />
            @enderror

            <!-- Prendas -->
            <div class="flex flex-col gap-3">
                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400">Estado de cada prenda</p>

                @foreach ($this->details as $detail)
                    @php($unit = $detail->physicalProduct)
                    @php($condition = $items[$detail->id]['condition'] ?? 'good')
                    <div wire:key="return-item-{{ $detail->id }}" class="rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p class="font-medium text-zinc-900 dark:text-white">{{ $unit?->variant?->product?->name }}</p>
                                <p class="font-mono text-xs text-zinc-500">{{ $unit?->code }} · {{ $unit?->variant?->label() }}</p>
                            </div>
                            <flux:radio.group wire:model.live="items.{{ $detail->id }}.condition" variant="segmented" size="sm">
                                @foreach ($conditions as $value => $label)
                                    <flux:radio value="{{ $value }}">{{ $label }}</flux:radio>
                                @endforeach
                            </flux:radio.group>
                        </div>

                        @if (in_array($condition, ['damaged', 'lost']))
                            <div class="mt-4 grid gap-3 sm:grid-cols-[10rem_1fr]">
                                <flux:input type="number" step="0.01" min="0" wire:model="items.{{ $detail->id }}.damage_fee"
                                    :label="$condition === 'lost' ? 'Cobro por pérdida (S/)' : 'Cobro por daño (S/)'" />
                                <flux:input wire:model="items.{{ $detail->id }}.damage_note" label="Detalle"
                                    :placeholder="$condition === 'lost' ? 'El cliente no devolvió la prenda...' : 'Mancha en la solapa, costura abierta...'" />
                            </div>
                            <p class="mt-1 text-[11px] text-zinc-500">
                                Lo que se cobra es un ingreso de la prenda, aunque luego la reparación cueste distinto (o la hagamos nosotros).
                            </p>
                        @endif

                        @if ($condition === 'lost')
                            <p class="mt-3 text-xs text-red-600 dark:text-red-400">La prenda se dará de baja como perdida y saldrá del stock.</p>
                        @else
                            <div class="mt-3 flex items-center gap-3 text-sm">
                                <span class="text-zinc-500">Después de recibirla:</span>
                                <flux:radio.group wire:model="items.{{ $detail->id }}.next_status" variant="segmented" size="sm">
                                    <flux:radio value="available">Disponible</flux:radio>
                                    <flux:radio value="maintenance">A mantenimiento</flux:radio>
                                </flux:radio.group>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <flux:textarea wire:model="return_notes" label="Observaciones" rows="3"
                placeholder="Ej. El cliente llegó tarde y hubo dificultad para acordar el cobro del daño." />

            <flux:callout icon="information-circle">
                <flux:callout.text>
                    Si el cliente quiere quedarse con una prenda, recepciona el alquiler normalmente y luego véndela con el botón
                    «Vender» que aparecerá en el resumen.
                </flux:callout.text>
            </flux:callout>

            <div class="flex flex-col-reverse gap-3 border-t border-zinc-100 pt-5 sm:flex-row sm:justify-end dark:border-zinc-800">
                <flux:button :href="route('rental.rentals.index')" wire:navigate variant="subtle">Cancelar</flux:button>
                <flux:button type="submit" variant="primary" icon="check">
                    <span wire:loading.remove wire:target="save">Confirmar recepción</span>
                    <span wire:loading wire:target="save">Guardando...</span>
                </flux:button>
            </div>
        </form>
    @endif
</div>
