<div>
    <div class="mb-6">
        <p class="mb-2 text-xs font-semibold text-violet-600 dark:text-violet-400">Nueva operación</p>
        <h1 class="text-2xl font-semibold text-zinc-900 dark:text-white">Registrar alquiler</h1>
    </div>

    @if ($redoFromCode)
        <flux:callout icon="arrow-path" class="mb-5"
            heading="Rehaciendo el alquiler anulado {{ $redoFromCode }}">
            <flux:callout.text>Revisa y corrige los datos antes de registrar. El alquiler anulado se conserva en el historial.</flux:callout.text>
        </flux:callout>
    @endif

    <div class="grid gap-5 lg:grid-cols-[1fr_340px]">
        <div class="flex flex-col gap-5">
            {{-- Galería --}}
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-white">Catálogo</h2>
                <livewire:shared.product-gallery mode="rental" fieldContext="rental-gallery" key="rental-gallery" />
                <livewire:shared.product-detail-modal key="rental-product-detail" />
                <livewire:shared.product-picker key="rental-product-picker" />
            </div>

            {{-- Carrito: cantidad/precio se calculan en Alpine (entangle) --}}
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"
                x-data="{
                    items: $wire.entangle('items'),
                    get subtotal() {
                        return Object.values(this.items).reduce((sum, i) =>
                            sum + (Number(i.quantity) || 0) * (Number(i.unit_price) || 0), 0);
                    },
                }"
            >
                <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-white">Prendas a alquilar</h2>

                @error('items')
                    <flux:callout variant="danger" icon="exclamation-triangle" heading="{{ $message }}" class="mb-4" />
                @enderror

                @if (empty($items))
                    <div class="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-zinc-300 py-10 text-center dark:border-zinc-700">
                        <flux:icon icon="sparkles" class="size-7 text-violet-500" />
                        <span class="text-sm text-zinc-500">Agrega prendas desde el catálogo de arriba.</span>
                    </div>
                @else
                    <div class="flex flex-col gap-3">
                        @foreach ($items as $key => $item)
                            <div class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-700" wire:key="rental-item-{{ $key }}">
                                <div class="flex items-start gap-3">
                                    <span class="grid size-9 place-items-center rounded-lg bg-violet-100 text-violet-700 dark:bg-violet-950/60 dark:text-violet-300">
                                        <flux:icon icon="sparkles" class="size-4" />
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold text-zinc-900 dark:text-white">{{ $item['product_name'] }}</p>
                                        <p class="text-xs text-zinc-500">
                                            {{ $item['product_code'] }}
                                            @if (! empty($item['variant_label']))
                                                · {{ $item['variant_label'] }}
                                            @endif
                                        </p>
                                    </div>
                                    @if (! empty($item['physical_product_id']))
                                        <flux:badge size="sm" color="violet" icon="qr-code">{{ $item['physical_code'] }}</flux:badge>
                                    @else
                                        {{-- Viene de "Rehacer" y la prenda original ya no está disponible --}}
                                        <flux:badge size="sm" color="amber">Se asignará una disponible</flux:badge>
                                    @endif
                                    <flux:button icon="trash" variant="subtle" size="sm" wire:click="removeItem('{{ $key }}')" />
                                </div>

                                <div class="mt-3 max-w-48">
                                    <flux:input type="number" step="0.01" size="sm" label="Precio alquiler" x-model.number="items['{{ $key }}'].unit_price" />
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="mt-5 flex items-center justify-between rounded-xl bg-zinc-50 p-4 text-base font-bold text-zinc-900 dark:bg-zinc-800/60 dark:text-white">
                    <span>Total alquiler</span>
                    <span x-text="'S/ ' + subtotal.toFixed(2)"></span>
                </div>
                <p class="mt-1 text-[11px] text-zinc-500">Los alquileres no llevan IGV desglosado en este flujo.</p>
            </div>
        </div>

        {{-- Datos del alquiler --}}
        <aside class="h-fit rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
            <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-white">Datos del alquiler</h2>

            <div class="flex flex-col gap-4">
                <div>
                    <flux:label>Cliente *</flux:label>
                    <div class="mt-1 flex items-start gap-2">
                        <div class="min-w-0 flex-1">
                            <livewire:shared.entity-search-select
                                entityType="client"
                                fieldContext="rental-client"
                                :selectedId="$client_id"
                                :selectedLabel="$client_name"
                                :key="'rental-client-'.$clientPickerVersion"
                            />
                        </div>
                        @can('clientes.registrar')
                        <flux:button type="button" icon="plus" variant="outline" wire:click="$dispatch('open-client-modal')"
                            title="Nuevo cliente" aria-label="Nuevo cliente" />
                        @endcan
                    </div>
                    @error('client_id')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <flux:input wire:model="pickup_date" type="datetime-local" label="Fecha de recojo" />
                <flux:input wire:model="expected_return_date" type="datetime-local" label="Devolución prevista" />
                <flux:input wire:model="deposit_amount" type="number" step="0.01" label="Garantía / adelanto (S/)" />

                <flux:textarea wire:model="notes" label="Notas" rows="2" />
            </div>

            <flux:button wire:click="save" variant="primary" class="mt-6 w-full" icon="document-check">
                <span wire:loading.remove wire:target="save">Registrar alquiler</span>
                <span wire:loading wire:target="save">Guardando...</span>
            </flux:button>
        </aside>
    </div>

    <livewire:core.client-form-modal key="rental-client-form" />
</div>
