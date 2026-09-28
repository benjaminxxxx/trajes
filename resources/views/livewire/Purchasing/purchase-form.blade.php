<div>
    <div class="mb-6">
        <div class="mb-2 flex items-center gap-2 text-xs font-semibold text-violet-600 dark:text-violet-400">
            <span class="grid size-6 place-items-center rounded-lg bg-violet-100 dark:bg-violet-950/60">
                <flux:icon icon="document-plus" class="size-3.5 text-violet-600 dark:text-violet-400" />
            </span>
            Nueva operación
        </div>
        <h1 class="text-2xl font-semibold text-zinc-900 dark:text-white">Registrar compra</h1>
        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
            Ingresa el comprobante y los productos que entrarán a tu inventario.
        </p>
    </div>

    <form wire:submit="save" class="grid gap-5 lg:grid-cols-[1fr_340px]">
        <div class="flex flex-col gap-5">
            {{-- Datos de la compra --}}
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-white">Datos de la compra</h2>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <flux:label>Proveedor</flux:label>
                        <div class="mt-1 flex items-start gap-2">
                            <div class="min-w-0 flex-1">
                                <livewire:shared.entity-search-select
                                    entityType="supplier"
                                    fieldContext="purchase-supplier"
                                    :selectedId="$supplier_id"
                                    :selectedLabel="$supplier_name"
                                    :key="'purchase-supplier-'.$supplierPickerVersion"
                                />
                            </div>
                            @can('proveedores.registrar')
                            <flux:button type="button" icon="plus" variant="outline" wire:click="$dispatch('open-supplier-modal')"
                                title="Nuevo proveedor" aria-label="Nuevo proveedor" />
                            @endcan
                        </div>
                    </div>

                    <flux:select wire:model.live="document_type" label="Tipo de comprobante"
                        :description="$document_type === 'factura'
                            ? 'Costo de cada prenda: precio SIN IGV (el IGV es crédito fiscal).'
                            : 'Costo de cada prenda: pago total CON IGV (no hay crédito fiscal).'">
                        <flux:select.option value="nota_venta">Nota de venta</flux:select.option>
                        <flux:select.option value="boleta">Boleta</flux:select.option>
                        <flux:select.option value="factura">Factura</flux:select.option>
                    </flux:select>

                    <flux:input wire:model="document_number" label="N.º de comprobante" placeholder="F001-0000123" />

                    <flux:input wire:model="issue_date" type="date" label="Fecha de emisión" />
                </div>

                <label
                    class="mt-5 flex cursor-pointer items-center gap-3 rounded-xl border border-violet-100 bg-violet-50/70 p-3 dark:border-violet-900/50 dark:bg-violet-950/20">
                    <flux:checkbox wire:model="has_invoice_pending" />
                    <span>
                        <span class="block text-sm font-medium text-zinc-800 dark:text-zinc-200">Por facturar</span>
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">El proveedor enviará la factura
                            después.</span>
                    </span>
                </label>

                <div class="mt-4">
                    <flux:textarea wire:model="notes" label="Notas" rows="2" />
                </div>
            </div>

            {{-- Productos --}}
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">Productos y variantes</h2>
                </div>

                <div class="mb-4">
                    <livewire:shared.entity-search-select
                        entityType="product"
                        fieldContext="purchase-product"
                        :resetAfterSelect="true"
                        key="purchase-product-search"
                    />
                </div>

                @error('items')
                    <flux:callout variant="danger" icon="exclamation-triangle" heading="{{ $message }}" class="mb-4" />
                @enderror

                @if (empty($items))
                    <div class="flex w-full flex-col items-center gap-2 rounded-2xl border border-dashed border-zinc-300 py-10 text-center dark:border-zinc-700">
                        <flux:icon icon="cube" class="size-7 text-violet-500" />
                        <span class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">Aún no hay productos</span>
                        <span class="text-xs text-zinc-500">Busca un producto arriba para agregarlo a la compra.</span>
                    </div>
                @else
                    <div class="flex flex-col gap-3">
                        @foreach ($items as $key => $item)
                            <div class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-700" wire:key="item-{{ $key }}">
                                <div class="flex items-start gap-3">
                                    <span
                                        class="grid size-9 place-items-center rounded-lg bg-violet-100 text-violet-700 dark:bg-violet-950/60 dark:text-violet-300">
                                        <flux:icon icon="shopping-cart" class="size-4" />
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold text-zinc-900 dark:text-white">
                                            {{ $item['product_name'] }}</p>
                                        <p class="text-xs text-zinc-500">{{ $item['product_code'] }}</p>
                                    </div>
                                    <flux:button icon="square-2-stack" variant="subtle" size="sm"
                                        wire:click="duplicateItem('{{ $key }}')" title="Duplicar fila (otra talla/color)" />
                                    <flux:button icon="trash" variant="subtle" size="sm"
                                        wire:click="removeItem('{{ $key }}')" />
                                </div>

                                @if ($this->isDuplicateVariant($key))
                                    <p class="mt-2 flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400">
                                        <flux:icon icon="information-circle" class="size-3.5" />
                                        Ya tienes esta talla y color en otra fila. Si es la misma compra, súmalas
                                        en una sola línea; si viene a un precio distinto (ej. descuento), déjalas
                                        separadas.
                                    </p>
                                @endif

                                <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-5">
                                    <flux:select size="sm" label="Talla" wire:model.live="items.{{ $key }}.size_id">
                                        <flux:select.option value="">Sin talla</flux:select.option>
                                        @foreach ($this->sizeOptions($item['category_id']) as $size)
                                            <flux:select.option value="{{ $size->id }}">{{ $size->name }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <flux:select size="sm" label="Color" wire:model.live="items.{{ $key }}.color_id">
                                        <flux:select.option value="">Sin color</flux:select.option>
                                        @foreach ($this->colorOptions($item['category_id']) as $color)
                                            <flux:select.option value="{{ $color->id }}">{{ $color->name }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <flux:input type="number" min="1" size="sm" label="Cantidad"
                                        wire:model.live="items.{{ $key }}.quantity" />
                                    <flux:input type="number" step="0.01" size="sm" label="P. unitario"
                                        wire:model.live="items.{{ $key }}.unit_price" />
                                    <flux:input type="number" step="0.01" size="sm" label="Dcto. %"
                                        wire:model.live="items.{{ $key }}.discount_percent" />
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Resumen --}}
        <aside class="h-fit rounded-2xl bg-zinc-950 p-5 text-white dark:bg-zinc-900">
            <p class="text-xs font-semibold uppercase tracking-wide text-violet-300">Resumen</p>
            <h2 class="mt-1 text-xl font-semibold">Totales</h2>

            <div class="mt-6 flex flex-col gap-3 text-sm">
                <div class="flex justify-between text-zinc-400">
                    <span>Subtotal</span>
                    <span class="font-semibold text-white">S/ {{ number_format($this->subtotal, 2) }}</span>
                </div>
                <div class="flex justify-between text-zinc-400">
                    <span>IGV (18%)</span>
                    <span class="font-semibold text-white">S/ {{ number_format($this->tax, 2) }}</span>
                </div>
                <div class="my-1 h-px bg-white/10"></div>
                <div class="flex items-end justify-between">
                    <span class="text-zinc-300">Total compra</span>
                    <strong class="text-2xl">S/ {{ number_format($this->total, 2) }}</strong>
                </div>
            </div>

            <flux:button type="submit" variant="primary" class="mt-6 w-full" icon="document-check">
                <span wire:loading.remove wire:target="save">Registrar compra</span>
                <span wire:loading wire:target="save">Guardando...</span>
            </flux:button>

            <p class="mt-3 text-center text-[11px] leading-5 text-zinc-500">
                Al guardar, el stock se actualizará según la variante (talla + color) seleccionada.
            </p>
        </aside>
    </form>

    <livewire:purchasing.supplier-modal key="purchase-supplier-form" />
</div>
