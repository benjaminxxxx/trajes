<div>
    <div class="mb-6">
        <p class="mb-2 text-xs font-semibold text-violet-600 dark:text-violet-400">Nueva operación</p>
        <h1 class="text-2xl font-semibold text-zinc-900 dark:text-white">Registrar venta</h1>
    </div>

    <div class="grid gap-5 lg:grid-cols-[1fr_340px]">
        <div class="flex flex-col gap-5">
            {{-- Galería --}}
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-white">Catálogo</h2>
                <livewire:shared.product-gallery mode="sale" fieldContext="sale-gallery" key="sale-gallery" />
                <livewire:shared.product-detail-modal key="sale-product-detail" />
            </div>

            {{-- Carrito: todo lo numérico se calcula en Alpine (entangle), sin pegarle al servidor por cada tecla --}}
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"
                x-data="{
                    items: $wire.entangle('items'),
                    get subtotal() {
                        return Object.values(this.items).reduce((sum, i) =>
                            sum + (Number(i.quantity) || 0) * (Number(i.unit_price) || 0) * (1 - (Number(i.discount_percent) || 0) / 100), 0);
                    },
                    get tax() { return this.subtotal * 0.18; },
                    get total() { return this.subtotal + this.tax; },
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
                                        <p class="text-xs text-zinc-500">{{ $item['product_code'] }}</p>
                                    </div>
                                    <flux:button icon="square-2-stack" variant="subtle" size="sm"
                                        wire:click="duplicateItem('{{ $key }}')" title="Duplicar fila (otra talla/color)" />
                                    <flux:button icon="trash" variant="subtle" size="sm" wire:click="removeItem('{{ $key }}')" />
                                </div>

                                @if ($this->isDuplicateVariant($key))
                                    <p class="mt-2 flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400">
                                        <flux:icon icon="information-circle" class="size-3.5" />
                                        Misma talla/color que otra fila.
                                    </p>
                                @endif

                                <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-5">
                                    <flux:select size="sm" label="Talla" x-model="items['{{ $key }}'].size_id">
                                        <flux:select.option value="">Sin talla</flux:select.option>
                                        @foreach ($this->sizeOptions($item['category_id']) as $size)
                                            <flux:select.option value="{{ $size->id }}">{{ $size->name }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <flux:select size="sm" label="Color" x-model="items['{{ $key }}'].color_id">
                                        <flux:select.option value="">Sin color</flux:select.option>
                                        @foreach ($this->colorOptions($item['category_id']) as $color)
                                            <flux:select.option value="{{ $color->id }}">{{ $color->name }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <flux:input type="number" min="1" size="sm" label="Cantidad" x-model.number="items['{{ $key }}'].quantity" />
                                    <flux:input type="number" step="0.01" size="sm" label="P. venta" x-model.number="items['{{ $key }}'].unit_price" />
                                    <flux:input type="number" step="0.01" size="sm" label="Dcto. %" x-model.number="items['{{ $key }}'].discount_percent" />
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Resumen inline, actualizado en vivo por Alpine --}}
                <div class="mt-5 rounded-xl bg-zinc-50 p-4 text-sm dark:bg-zinc-800/60">
                    <div class="flex justify-between text-zinc-500">
                        <span>Subtotal</span>
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
                    <div class="mt-1">
                        <livewire:shared.entity-search-select
                            entityType="client"
                            fieldContext="sale-client"
                            :selectedId="$client_id"
                            :selectedLabel="$client_name"
                            :key="'sale-client-'.$clientPickerVersion"
                        />
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
</div>