<div>
    <flux:modal wire:model="isOpen" class="w-full max-w-3xl">
        @if ($isOpen && $this->product)
            @php($product = $this->product)
            @php($selectedVariant = $this->selectedVariant)

            <div class="flex flex-col gap-5">
                <!-- Paso 1: perfil del producto -->
                <div class="flex gap-4 border-b border-zinc-100 pb-4 dark:border-zinc-800">
                    <div class="size-20 shrink-0 overflow-hidden rounded-xl bg-zinc-100 dark:bg-zinc-800">
                        @if ($this->coverUrl)
                            <img src="{{ $this->coverUrl }}" alt="{{ $product->name }}" class="size-full object-cover" />
                        @else
                            <div class="grid size-full place-items-center">
                                <flux:icon icon="photo" class="size-6 text-zinc-300 dark:text-zinc-600" />
                            </div>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-wider text-violet-500">
                            {{ $product->category?->name ?? 'Sin categoría' }} · {{ $product->code }}
                        </p>
                        <h2 class="mt-1 text-xl font-semibold text-zinc-900 dark:text-white">{{ $product->name }}</h2>
                        <p class="mt-1 text-sm text-zinc-500">
                            @if ($this->tracksUnits)
                                Elige la talla/color y luego {{ $mode === 'rental' ? 'las prendas a alquilar' : 'la prenda exacta a vender' }} por su código.
                            @else
                                Elige la talla/color y la cantidad. Solo se muestran combinaciones con stock.
                            @endif
                        </p>
                    </div>
                </div>

                <!-- Paso 2: talla y color con disponibilidad -->
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-zinc-400">1. Talla y color</p>
                    @if ($this->variants->where('available', '>', 0)->isEmpty())
                        <flux:callout variant="warning" icon="archive-box" heading="Sin disponibilidad">
                            <flux:callout.text>
                                {{ $this->tracksUnits ? 'No hay prendas disponibles de este producto en este momento.' : 'Este producto no tiene stock en ninguna talla/color.' }}
                            </flux:callout.text>
                        </flux:callout>
                    @else
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach ($this->variants as $variant)
                                <button type="button" wire:key="picker-variant-{{ $variant->id }}"
                                    wire:click="selectVariant({{ $variant->id }})"
                                    @disabled($variant->available === 0)
                                    @class([
                                        'flex items-center justify-between gap-3 rounded-xl border px-3 py-2 text-left text-sm transition-colors',
                                        'border-violet-500 bg-violet-50/60 dark:border-violet-500/60 dark:bg-violet-950/30' => $variantId === $variant->id,
                                        'border-zinc-200 hover:border-zinc-300 dark:border-zinc-700' => $variantId !== $variant->id && $variant->available > 0,
                                        'cursor-not-allowed border-zinc-100 opacity-50 dark:border-zinc-800' => $variant->available === 0,
                                    ])>
                                    <span class="flex items-center gap-2">
                                        @if ($variant->color)
                                            <span class="size-3 rounded-full border border-zinc-300"
                                                style="background-color: {{ $variant->color->hex_code ?? '#a1a1aa' }}"></span>
                                        @endif
                                        <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $variant->label() }}</span>
                                    </span>
                                    <span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $variant->available > 0 ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300' : 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800' }}">
                                        {{ $variant->available }} {{ $this->tracksUnits ? 'disp.' : 'en stock' }}
                                    </span>
                                </button>
                            @endforeach
                        </div>
                        <flux:error name="variantId" class="mt-2" />
                    @endif
                </div>

                <!-- Paso 3: prendas por código o cantidad -->
                @if ($selectedVariant)
                    @if ($this->tracksUnits)
                        <div>
                            <div class="mb-2 flex items-center justify-between">
                                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400">2. Prendas disponibles</p>
                                <span class="text-xs text-zinc-500">{{ count($selectedUnitIds) }} seleccionadas</span>
                            </div>

                            <div class="max-h-72 overflow-y-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
                                <table class="w-full text-sm">
                                    <thead class="sticky top-0 bg-zinc-50 text-xs text-zinc-500 dark:bg-zinc-800">
                                        <tr>
                                            <th class="w-10 px-3 py-2"></th>
                                            <th class="px-3 py-2 text-left font-semibold">Código</th>
                                            <th class="px-3 py-2 text-right font-semibold">Veces alquilada</th>
                                            <th class="px-3 py-2 text-right font-semibold">{{ $mode === 'rental' ? 'P. alquiler' : 'P. venta' }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                        @foreach ($this->units as $unit)
                                            @php($isSelected = in_array($unit->id, $selectedUnitIds))
                                            <tr wire:key="picker-unit-{{ $unit->id }}" wire:click="toggleUnit({{ $unit->id }})"
                                                @class([
                                                    'cursor-pointer transition-colors',
                                                    'bg-violet-50/70 dark:bg-violet-950/30' => $isSelected,
                                                    'hover:bg-zinc-50 dark:hover:bg-zinc-800/40' => !$isSelected,
                                                ])>
                                                <td class="px-3 py-2">
                                                    <span @class([
                                                        'grid size-5 place-items-center rounded border',
                                                        'border-violet-600 bg-violet-600 text-white' => $isSelected,
                                                        'border-zinc-300 dark:border-zinc-600' => !$isSelected,
                                                    ])>
                                                        @if ($isSelected)
                                                            <flux:icon icon="check" class="size-3.5" />
                                                        @endif
                                                    </span>
                                                </td>
                                                <td class="px-3 py-2">
                                                    <span class="font-mono text-xs font-semibold text-zinc-800 dark:text-zinc-200">{{ $unit->code }}</span>
                                                    @if ($unit->isReserved())
                                                        {{-- Solo aviso: se puede elegir igual si el cliente llegó antes --}}
                                                        <span class="mt-0.5 flex items-center gap-1 text-[11px] {{ $unit->isReservationExpired() ? 'text-zinc-400' : 'text-amber-600 dark:text-amber-400' }}">
                                                            <flux:icon icon="bookmark" class="size-3" />
                                                            Separada: {{ $unit->reservedClient?->display_name }} · {{ $unit->reserved_until?->format('d/m') }}{{ $unit->isReservationExpired() ? ' (vencida)' : '' }}
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-2 text-right tabular-nums text-zinc-600 dark:text-zinc-300">{{ $unit->rental_details_count }}</td>
                                                <td class="px-3 py-2 text-right tabular-nums">S/ {{ number_format($unit->suggested_price, 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <flux:error name="selectedUnitIds" class="mt-2" />
                        </div>
                    @else
                        <div>
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-zinc-400">2. Cantidad</p>
                            <div class="flex items-end gap-3">
                                <flux:input type="number" wire:model="quantity" min="1" max="{{ $selectedVariant->available }}"
                                    label="Unidades" class="w-32" />
                                <p class="pb-2 text-sm text-zinc-500">
                                    de {{ $selectedVariant->available }} en stock ·
                                    S/ {{ number_format((float) ($selectedVariant->sale_price ?? $product->price), 2) }} c/u
                                </p>
                            </div>
                        </div>
                    @endif
                @endif

                <div class="flex justify-end gap-2 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                    <flux:modal.close>
                        <flux:button variant="subtle">Cancelar</flux:button>
                    </flux:modal.close>
                    <flux:button wire:click="addSelected" variant="primary" icon="plus"
                        :disabled="! $selectedVariant || ($this->tracksUnits && empty($selectedUnitIds))">
                        Agregar al carrito
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
