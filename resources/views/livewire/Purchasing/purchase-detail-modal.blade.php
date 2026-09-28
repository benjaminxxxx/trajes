<div>
    <flux:modal wire:model="isOpen" class="w-full max-w-4xl">
        @if ($isOpen && $this->purchase)
            @php($purchase = $this->purchase)

            <div class="flex flex-col gap-5">
                <!-- Header -->
                <div class="border-b border-zinc-100 pb-4 dark:border-zinc-800">
                    <div class="mb-2 flex items-center gap-2 text-xs font-semibold text-violet-600 dark:text-violet-400">
                        <span class="grid size-6 place-items-center rounded-lg bg-violet-100 dark:bg-violet-950/60">
                            <flux:icon icon="shopping-bag" class="size-3.5 text-violet-600 dark:text-violet-400" />
                        </span>
                        Editar compra
                    </div>
                    <h2 class="text-2xl font-semibold text-zinc-900 dark:text-white">Compra #{{ $purchase->id }}</h2>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                        Registrada por {{ $purchase->user?->name ?? '—' }} el {{ $purchase->created_at?->format('d/m/Y H:i') }}
                    </p>
                </div>

                <!-- Cabecera editable (no afecta stock) -->
                <form wire:submit="save" class="flex flex-col gap-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400">Datos de la compra</p>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <flux:field>
                                <flux:label>Proveedor</flux:label>
                                <livewire:shared.entity-search-select entityType="supplier" fieldContext="purchase-edit-supplier"
                                    :selectedId="$supplier_id" :selectedLabel="$supplier_name"
                                    :key="'purchase-edit-supplier-' . $purchase->id" />
                            </flux:field>
                        </div>

                        <flux:select wire:model.live="document_type" label="Tipo de comprobante"
                            :description="$document_type === 'factura'
                                ? 'Costo sin IGV (crédito fiscal). Cambiarlo recalcula el costo de las prendas.'
                                : 'Costo con IGV (sin crédito fiscal). Cambiarlo recalcula el costo de las prendas.'">
                            @foreach (\App\Models\Sale::DOCUMENT_TYPES as $value => $documentLabel)
                                <flux:select.option value="{{ $value }}">{{ $documentLabel }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:input wire:model="document_number" label="N° de comprobante" placeholder="F001-0000123" />

                        <flux:input wire:model="issue_date" type="date" label="Fecha de compra" required />

                        <div class="flex items-end pb-2">
                            <flux:switch wire:model="has_invoice_pending" label="Factura pendiente" align="left" />
                        </div>

                        <div class="sm:col-span-2">
                            <flux:textarea wire:model="notes" label="Notas" rows="2" />
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <flux:button type="submit" variant="primary">
                            <span wire:loading.remove wire:target="save">Guardar datos</span>
                            <span wire:loading wire:target="save">Guardando...</span>
                        </flux:button>
                    </div>
                </form>

                <!-- Detalle: solo se permite reducir cantidades -->
                <div>
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wider text-zinc-400">Detalle</p>
                    <p class="mb-3 text-xs text-zinc-500">
                        Solo puedes reducir cantidades. En productos de alquiler solo se quitan prendas que nunca se
                        alquilaron, vendieron ni pasaron por mantenimiento.
                    </p>

                    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
                        <table class="w-full min-w-[44rem] text-sm">
                            <thead class="bg-zinc-50 text-xs text-zinc-500 dark:bg-zinc-800/60">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold">Producto</th>
                                    <th class="px-3 py-2 text-right font-semibold">P. unit.</th>
                                    <th class="px-3 py-2 text-right font-semibold">Dcto.</th>
                                    <th class="px-3 py-2 text-right font-semibold">Subtotal</th>
                                    <th class="px-3 py-2 text-left font-semibold">Cantidad</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @foreach ($purchase->details as $detail)
                                    @php($removable = $this->removable[$detail->id] ?? 0)
                                    <tr wire:key="purchase-detail-{{ $detail->id }}" class="align-top">
                                        <td class="px-3 py-2">
                                            <p class="font-medium text-zinc-800 dark:text-zinc-200">{{ $detail->variant?->product?->name }}</p>
                                            <p class="text-xs text-zinc-500">
                                                {{ $detail->variant?->label() }}
                                                @if ($detail->variant?->product?->allow_rental)
                                                    · <span class="text-violet-600 dark:text-violet-400">Con prendas físicas</span>
                                                @endif
                                            </p>
                                        </td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ number_format($detail->unit_price, 2) }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ (float) $detail->discount_percent > 0 ? number_format($detail->discount_percent, 2) . '%' : '—' }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ number_format($detail->subtotal, 2) }}</td>
                                        <td class="px-3 py-2">
                                            <div class="flex items-center gap-2">
                                                <flux:input type="number" size="sm" min="1" max="{{ $detail->quantity }}"
                                                    wire:model="quantities.{{ $detail->id }}" class="w-20"
                                                    :disabled="$removable === 0" />
                                                @if ($removable > 0)
                                                    <flux:button size="sm" wire:click="reduceQuantity({{ $detail->id }})"
                                                        wire:confirm="¿Reducir la cantidad de esta línea? Se ajustará el stock.">
                                                        Corregir
                                                    </flux:button>
                                                @endif
                                            </div>
                                            <p class="mt-1 text-[11px] {{ $removable > 0 ? 'text-zinc-500' : 'text-amber-600 dark:text-amber-400' }}">
                                                {{ $removable > 0 ? "Puedes quitar hasta {$removable}" : 'No se puede reducir' }}
                                            </p>
                                            <flux:error name="quantities.{{ $detail->id }}" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="border-t border-zinc-200 text-sm dark:border-zinc-700">
                                <tr>
                                    <td colspan="3" class="px-3 py-1.5 text-right text-zinc-500">Subtotal</td>
                                    <td class="px-3 py-1.5 text-right tabular-nums">{{ number_format($purchase->subtotal, 2) }}</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="px-3 py-1.5 text-right text-zinc-500">IGV</td>
                                    <td class="px-3 py-1.5 text-right tabular-nums">{{ number_format($purchase->tax, 2) }}</td>
                                    <td></td>
                                </tr>
                                <tr class="font-semibold">
                                    <td colspan="3" class="px-3 py-1.5 text-right">Total</td>
                                    <td class="px-3 py-1.5 text-right tabular-nums">S/ {{ number_format($purchase->total, 2) }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <div class="flex justify-end border-t border-zinc-100 pt-5 dark:border-zinc-800">
                    <flux:modal.close>
                        <flux:button variant="subtle">Cerrar</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
