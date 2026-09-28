<div>
    <div class="mb-6">
        <p class="mb-2 text-xs font-semibold text-violet-600 dark:text-violet-400">Nueva operación</p>
        <h1 class="text-2xl font-semibold text-zinc-900 dark:text-white">Registrar venta</h1>
    </div>

    @if ($redoFromCode)
        <flux:callout icon="arrow-path" class="mb-5"
            heading="Rehaciendo la venta anulada {{ $redoFromCode }}">
            <flux:callout.text>Revisa y corrige los datos antes de registrar. La venta anulada se conserva en el historial.</flux:callout.text>
        </flux:callout>
    @endif

    <div class="grid gap-5 lg:grid-cols-[1fr_340px]">
        <div class="flex flex-col gap-5">
            {{-- Galería --}}
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-white">Catálogo</h2>
                <livewire:shared.product-gallery mode="sale" fieldContext="sale-gallery" key="sale-gallery" />
                <livewire:shared.product-detail-modal key="sale-product-detail" />
                <livewire:shared.product-picker key="sale-product-picker" />
            </div>

            {{-- Carrito: todo lo numérico se calcula en Alpine (entangle), sin pegarle al servidor por cada tecla.
                 Los precios se ingresan CON IGV (lo que se le dice al cliente) y el IGV se desglosa hacia atrás. --}}
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"
                x-data="{
                    items: $wire.entangle('items'),
                    round2(value) { return Math.round((Number(value) || 0) * 100) / 100; },
                    fromPrice(key) {
                        const item = this.items[key];
                        item.line_total = this.round2((Number(item.quantity) || 0) * (Number(item.unit_price) || 0));
                    },
                    fromLineTotal(key) {
                        const item = this.items[key];
                        const quantity = Number(item.quantity) || 1;
                        item.unit_price = this.round2((Number(item.line_total) || 0) / quantity);
                    },
                    lineTax(key) {
                        const total = Number(this.items[key]?.line_total) || 0;
                        return total - total / 1.18;
                    },
                    get total() {
                        return Object.values(this.items).reduce((sum, i) => sum + (Number(i.line_total) || 0), 0);
                    },
                    get subtotal() { return this.total / 1.18; },
                    get tax() { return this.total - this.subtotal; },
                }"
            >
                <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-white">Detalle de la venta</h2>

                @error('items')
                    <flux:callout variant="danger" icon="exclamation-triangle" heading="{{ $message }}" class="mb-4" />
                @enderror

                @if (empty($items))
                    <div class="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-zinc-300 py-10 text-center dark:border-zinc-700">
                        <flux:icon icon="shopping-cart" class="size-7 text-violet-500" />
                        <span class="text-sm text-zinc-500">Agrega productos desde el catálogo de arriba.</span>
                    </div>
                @else
                    <div class="flex flex-col gap-3">
                        @foreach ($items as $key => $item)
                            <div class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-700" wire:key="sale-item-{{ $key }}">
                                <div class="flex items-start gap-3">
                                    <span class="grid size-9 place-items-center rounded-lg bg-violet-100 text-violet-700 dark:bg-violet-950/60 dark:text-violet-300">
                                        <flux:icon icon="shopping-bag" class="size-4" />
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
                                    @endif
                                    <flux:button icon="trash" variant="subtle" size="sm" wire:click="removeItem('{{ $key }}')" />
                                </div>

                                @php($isUnit = ! empty($item['physical_product_id']))
                                <div class="mt-3 grid grid-cols-2 gap-2 {{ $isUnit ? 'sm:grid-cols-3' : 'sm:grid-cols-4' }}">
                                    @unless ($isUnit)
                                        <flux:input type="number" min="1" size="sm" label="Cantidad"
                                            x-model.number="items['{{ $key }}'].quantity" x-on:input="fromPrice('{{ $key }}')" />
                                    @endunless
                                    <flux:input type="number" step="0.01" min="0" size="sm" label="P. unit. (inc. IGV)"
                                        x-model.number="items['{{ $key }}'].unit_price" x-on:input="fromPrice('{{ $key }}')" />
                                    <flux:input type="number" step="0.01" min="0" size="sm" label="Subtotal (inc. IGV)"
                                        x-model.number="items['{{ $key }}'].line_total" x-on:input="fromLineTotal('{{ $key }}')" />
                                    <div class="flex flex-col justify-end pb-2 text-xs text-zinc-500">
                                        IGV: <span class="font-medium text-zinc-700 dark:text-zinc-300" x-text="'S/ ' + lineTax('{{ $key }}').toFixed(2)"></span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Resumen inline, actualizado en vivo por Alpine --}}
                <div class="mt-5 rounded-xl bg-zinc-50 p-4 text-sm dark:bg-zinc-800/60">
                    <div class="flex justify-between text-zinc-500">
                        <span>Op. gravada (sin IGV)</span>
                        <span x-text="'S/ ' + subtotal.toFixed(2)"></span>
                    </div>
                    <div class="mt-1 flex justify-between text-zinc-500">
                        <span>IGV (18%)</span>
                        <span x-text="'S/ ' + tax.toFixed(2)"></span>
                    </div>
                    <div class="mt-2 flex justify-between border-t border-zinc-200 pt-2 text-base font-bold text-zinc-900 dark:border-zinc-700 dark:text-white">
                        <span>Total</span>
                        <span x-text="'S/ ' + total.toFixed(2)"></span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Datos de la venta --}}
        <aside class="h-fit rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
            <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-white">Datos de la venta</h2>

            <div class="flex flex-col gap-4">
                <div>
                    <flux:label>Cliente</flux:label>
                    <div class="mt-1 flex items-start gap-2">
                        <div class="min-w-0 flex-1">
                            <livewire:shared.entity-search-select
                                entityType="client"
                                fieldContext="sale-client"
                                :selectedId="$client_id"
                                :selectedLabel="$client_name"
                                :key="'sale-client-'.$clientPickerVersion"
                            />
                        </div>
                        @can('clientes.registrar')
                        <flux:button type="button" icon="plus" variant="outline" wire:click="$dispatch('open-client-modal')"
                            title="Nuevo cliente" aria-label="Nuevo cliente" />
                        @endcan
                    </div>
                    <p class="mt-1 text-[11px] text-zinc-500">Opcional: puedes registrar la venta sin cliente.</p>
                </div>

                <flux:select wire:model="document_type" label="Tipo de comprobante">
                    <flux:select.option value="nota_venta">Nota de venta</flux:select.option>
                    <flux:select.option value="boleta">Boleta</flux:select.option>
                    <flux:select.option value="factura">Factura</flux:select.option>
                </flux:select>

                <flux:input wire:model="document_number" label="N.º de comprobante" placeholder="F001-0000123" />
                <flux:input wire:model="sale_date" type="date" label="Fecha de venta" />

                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-violet-100 bg-violet-50/70 p-3 dark:border-violet-900/50 dark:bg-violet-950/20">
                    <flux:checkbox wire:model="has_invoice_pending" />
                    <span class="text-sm font-medium text-zinc-800 dark:text-zinc-200">Por facturar</span>
                </label>

                <flux:textarea wire:model="notes" label="Notas" rows="2" />
            </div>

            <flux:button wire:click="save" variant="primary" class="mt-6 w-full" icon="document-check">
                <span wire:loading.remove wire:target="save">Registrar venta</span>
                <span wire:loading wire:target="save">Guardando...</span>
            </flux:button>
        </aside>
    </div>

    <livewire:core.client-form-modal key="sale-client-form" />
</div>
