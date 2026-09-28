<div>
    <flux:modal wire:model="isOpen" class="w-full max-w-3xl">
        @if ($isOpen && $this->sale)
            @php($sale = $this->sale)

            <div class="flex flex-col gap-5">
                <!-- Header -->
                <div class="border-b border-zinc-100 pb-4 dark:border-zinc-800">
                    <div class="mb-2 flex items-center gap-2 text-xs font-semibold text-violet-600 dark:text-violet-400">
                        <span class="grid size-6 place-items-center rounded-lg bg-violet-100 dark:bg-violet-950/60">
                            <flux:icon icon="shopping-cart" class="size-3.5 text-violet-600 dark:text-violet-400" />
                        </span>
                        Venta
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ $sale->code }}</h2>
                        @if ($sale->isCancelled())
                            <flux:badge color="red">Anulada</flux:badge>
                        @else
                            <flux:badge color="emerald">Completada</flux:badge>
                            @if ($sale->has_invoice_pending)
                                <flux:badge color="amber">Por facturar</flux:badge>
                            @endif
                        @endif
                    </div>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                        Registrada por {{ $sale->user?->name ?? '—' }} el {{ $sale->created_at?->format('d/m/Y H:i') }}
                    </p>
                </div>

                @if ($sale->isCancelled())
                    <flux:callout variant="danger" icon="x-circle"
                        heading="Anulada el {{ $sale->cancelled_at?->format('d/m/Y H:i') }} por {{ $sale->cancelledBy?->name ?? '—' }}">
                        <flux:callout.text>{{ $sale->cancellation_reason }}</flux:callout.text>
                    </flux:callout>
                @endif

                <!-- Detalle (solo lectura: afecta stock) -->
                <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
                    <table class="w-full text-sm">
                        <thead class="bg-zinc-50 text-xs text-zinc-500 dark:bg-zinc-800/60">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold">Producto</th>
                                <th class="px-3 py-2 text-right font-semibold">Cant.</th>
                                <th class="px-3 py-2 text-right font-semibold">P. unit. s/IGV</th>
                                <th class="px-3 py-2 text-right font-semibold">Costo unit.</th>
                                <th class="px-3 py-2 text-right font-semibold">Margen</th>
                                <th class="px-3 py-2 text-right font-semibold">Subtotal s/IGV</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($sale->details as $detail)
                                @php($margin = (float) $detail->subtotal - (float) $detail->unit_cost * $detail->quantity)
                                <tr wire:key="sale-detail-{{ $detail->id }}">
                                    <td class="px-3 py-2">
                                        <p class="font-medium text-zinc-800 dark:text-zinc-200">{{ $detail->variant?->product?->name }}</p>
                                        <p class="text-xs text-zinc-500">
                                            {{ collect([$detail->variant?->size?->name ? 'Talla ' . $detail->variant->size->name : null, $detail->variant?->color?->name, $detail->physicalProduct?->code])->filter()->join(' · ') }}
                                        </p>
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $detail->quantity }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ number_format($detail->unit_price, 2) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-zinc-500">{{ number_format($detail->unit_cost, 2) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums {{ $margin >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                                        {{ number_format($margin, 2) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ number_format($detail->subtotal, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        @php($totalMargin = $sale->details->sum(fn ($d) => (float) $d->subtotal - (float) $d->unit_cost * $d->quantity))
                        <tfoot class="border-t border-zinc-200 text-sm dark:border-zinc-700">
                            <tr>
                                <td colspan="5" class="px-3 py-1.5 text-right text-zinc-500">Op. gravada</td>
                                <td class="px-3 py-1.5 text-right tabular-nums">{{ number_format($sale->subtotal, 2) }}</td>
                            </tr>
                            <tr>
                                <td colspan="5" class="px-3 py-1.5 text-right text-zinc-500">IGV</td>
                                <td class="px-3 py-1.5 text-right tabular-nums">{{ number_format($sale->tax, 2) }}</td>
                            </tr>
                            <tr>
                                <td colspan="5" class="px-3 py-1.5 text-right text-zinc-500">Margen bruto (sin IGV)</td>
                                <td class="px-3 py-1.5 text-right tabular-nums {{ $totalMargin >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ number_format($totalMargin, 2) }}</td>
                            </tr>
                            <tr class="font-semibold">
                                <td colspan="5" class="px-3 py-1.5 text-right">Total</td>
                                <td class="px-3 py-1.5 text-right tabular-nums">S/ {{ number_format($sale->total, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                @if ($sale->isCancelled())
                    <!-- Venta anulada: solo lectura, se puede rehacer con los datos corregidos -->
                    <div class="grid gap-3 text-sm sm:grid-cols-3">
                        <div>
                            <p class="text-xs text-zinc-500">Cliente</p>
                            <p class="font-medium">{{ $sale->client?->display_name ?? 'Cliente varios' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-zinc-500">Fecha</p>
                            <p class="font-medium">{{ $sale->sale_date?->format('d/m/Y') }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-zinc-500">Comprobante</p>
                            <p class="font-medium">{{ \App\Models\Sale::DOCUMENT_TYPES[$sale->document_type] ?? $sale->document_type }} {{ $sale->document_number }}</p>
                        </div>
                    </div>

                    <div class="flex flex-col-reverse gap-3 border-t border-zinc-100 pt-5 sm:flex-row sm:justify-end dark:border-zinc-800">
                        <flux:modal.close>
                            <flux:button variant="subtle">Cerrar</flux:button>
                        </flux:modal.close>
                        @can('ventas.registrar')
                            <flux:button :href="route('sale.sales.create', ['from' => $sale->id])" wire:navigate
                                variant="primary" icon="arrow-path">
                                Rehacer venta
                            </flux:button>
                        @endcan
                    </div>
                @elsecannot('ventas.editar')
                    <!-- Solo lectura: quien no puede editar debe pedir el cambio al administrador -->
                    <div class="grid gap-3 text-sm sm:grid-cols-3">
                        <div>
                            <p class="text-xs text-zinc-500">Cliente</p>
                            <p class="font-medium">{{ $sale->client?->display_name ?? 'Cliente varios' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-zinc-500">Fecha</p>
                            <p class="font-medium">{{ $sale->sale_date?->format('d/m/Y') }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-zinc-500">Comprobante</p>
                            <p class="font-medium">{{ \App\Models\Sale::DOCUMENT_TYPES[$sale->document_type] ?? $sale->document_type }} {{ $sale->document_number }}</p>
                        </div>
                    </div>
                    <flux:callout icon="information-circle">
                        <flux:callout.text>Si hay un error en esta venta, solicita la corrección o anulación al administrador.</flux:callout.text>
                    </flux:callout>
                    <div class="flex justify-end border-t border-zinc-100 pt-5 dark:border-zinc-800">
                        <flux:modal.close>
                            <flux:button variant="subtle">Cerrar</flux:button>
                        </flux:modal.close>
                    </div>
                @else
                    <!-- Datos editables (no afectan stock) -->
                    <form wire:submit="save" class="flex flex-col gap-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400">Datos de la venta</p>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <flux:field>
                                <flux:label>Cliente</flux:label>
                                <livewire:shared.entity-search-select entityType="client" fieldContext="sale-edit-client"
                                    :selectedId="$client_id" :selectedLabel="$client_name"
                                    :key="'sale-edit-client-' . $sale->id" />
                            </flux:field>

                            <flux:input wire:model="sale_date" type="date" label="Fecha de venta" required />

                            <flux:select wire:model.live="document_type" label="Tipo de comprobante">
                                @foreach (\App\Models\Sale::DOCUMENT_TYPES as $value => $documentLabel)
                                    <flux:select.option value="{{ $value }}">{{ $documentLabel }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:input wire:model="document_number" label="N° de comprobante" placeholder="B001-000123" />

                            <div class="sm:col-span-2">
                                <flux:textarea wire:model="notes" label="Notas" rows="2" />
                            </div>
                        </div>

                        @if ($has_invoice_pending)
                            <!-- Facturación -->
                            <div class="rounded-2xl border border-amber-200 bg-amber-50/60 p-4 dark:border-amber-900/60 dark:bg-amber-950/20">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                                    <div class="flex-1">
                                        <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">Pendiente de facturar</p>
                                        <p class="text-xs text-amber-700/80 dark:text-amber-400/80">
                                            Elige boleta o factura, ingresa el número emitido y la fecha.
                                        </p>
                                    </div>
                                    <flux:input wire:model="invoice_date" type="date" label="Fecha de emisión" class="sm:w-44" />
                                    <flux:button wire:click="invoice" icon="check-badge" class="shrink-0">
                                        Marcar facturada
                                    </flux:button>
                                </div>
                            </div>
                        @endif

                        <!-- Footer -->
                        <div
                            class="flex flex-col-reverse gap-3 border-t border-zinc-100 pt-5 sm:flex-row sm:items-center sm:justify-between dark:border-zinc-800">
                            <div>
                                @can('ventas.anular')
                                    <flux:modal.trigger name="cancel-sale">
                                        <flux:button variant="danger" icon="x-circle">Anular venta</flux:button>
                                    </flux:modal.trigger>
                                @endcan
                            </div>

                            <div class="flex flex-col-reverse gap-3 sm:flex-row">
                                <flux:modal.close>
                                    <flux:button variant="subtle">Cerrar</flux:button>
                                </flux:modal.close>
                                <flux:button type="submit" variant="primary">
                                    <span wire:loading.remove wire:target="save">Guardar cambios</span>
                                    <span wire:loading wire:target="save">Guardando...</span>
                                </flux:button>
                            </div>
                        </div>
                    </form>
                @endif
            </div>
        @endif
    </flux:modal>

    <!-- Confirmación de anulación -->
    <flux:modal name="cancel-sale" class="w-full max-w-md">
        <form wire:submit="cancel" class="flex flex-col gap-4">
            <div>
                <flux:heading size="lg">Anular venta {{ $this->sale?->code }}</flux:heading>
                <flux:text class="mt-1">
                    El stock de los productos se devolverá y se registrará la salida de caja. La venta no se borra:
                    queda como anulada y luego puedes rehacerla con los datos corregidos.
                </flux:text>
            </div>

            <flux:textarea wire:model="cancellationReason" label="Motivo de anulación" rows="3"
                placeholder="Ej. Talla equivocada, se registrará nuevamente." required />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="subtle">Volver</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger">Confirmar anulación</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
