<div>
    <flux:modal wire:model="isOpen" class="w-full max-w-2xl">
        @if ($isOpen && $saleId)
            @php($sale = $this->sale)
            @php($document = $this->document)

            <div class="flex flex-col gap-5">
                <div class="border-b border-zinc-100 pb-4 dark:border-zinc-800">
                    <div class="mb-2 flex items-center gap-2 text-xs font-semibold text-violet-600 dark:text-violet-400">
                        <span class="grid size-6 place-items-center rounded-lg bg-violet-100 dark:bg-violet-950/60">
                            <flux:icon icon="document-check" class="size-3.5 text-violet-600 dark:text-violet-400" />
                        </span>
                        Facturación electrónica
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-2xl font-semibold text-zinc-900 dark:text-white">Facturar venta {{ $sale->code }}</h2>
                        @if ($settings->environment && ! $settings->isProduction())
                            <flux:badge color="amber" size="sm">Modo prueba</flux:badge>
                        @endif
                    </div>
                    <p class="mt-1 text-sm text-zinc-500">Venta del {{ $sale->sale_date?->format('d/m/Y') }}</p>
                </div>

                @if ($document)
                    {{-- Resultado del envío (o comprobante pendiente de respuesta) --}}
                    <div class="rounded-2xl border border-zinc-200 p-4 dark:border-zinc-700" data-test="invoice-result">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p class="text-xs text-zinc-500">{{ $document->typeLabel() }} electrónica</p>
                                <p class="text-xl font-semibold">{{ $document->fullNumber() }}</p>
                            </div>
                            <flux:badge :color="$document->statusColor()">{{ $document->statusLabel() }}</flux:badge>
                        </div>
                        @if ($document->sunat_description)
                            <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-400">{{ $document->sunat_description }}</p>
                        @endif
                        @foreach ($document->sunat_notes ?? [] as $note)
                            <p class="mt-1 text-xs text-lime-700 dark:text-lime-400">Observación: {{ $note }}</p>
                        @endforeach

                        @if ($document->isTest())
                            <flux:callout icon="beaker" class="mt-3" data-test="test-document-notice">
                                <flux:callout.text>
                                    Emitido en <strong>modo prueba</strong>: no tiene valor tributario. La venta sigue pendiente de facturar
                                    y puede volver a facturarla las veces que quiera.
                                </flux:callout.text>
                            </flux:callout>
                        @endif

                        @if ($document->isAwaiting())
                            <flux:callout variant="warning" icon="clock" class="mt-3">
                                <flux:callout.text>
                                    @if ($document->status === \App\Models\SunatDocument::STATUS_OFFLINE)
                                        No hubo respuesta del servicio de facturación. Quedó guardado: reintente cuando haya conexión (no se duplicará).
                                    @else
                                        El comprobante quedó registrado y numerado, pero SUNAT aún no responde. Se reintenta automáticamente; puede consultar el estado.
                                    @endif
                                </flux:callout.text>
                            </flux:callout>
                        @elseif ($document->status === \App\Models\SunatDocument::STATUS_REJECTED)
                            <flux:callout variant="danger" icon="x-circle" class="mt-3">
                                <flux:callout.text>El comprobante no existe para SUNAT. Corrija el motivo (por ejemplo, el cliente de la venta) y vuelva a facturar.</flux:callout.text>
                            </flux:callout>
                        @endif
                    </div>

                    @if ($apiErrors)
                        <flux:callout variant="danger" icon="exclamation-triangle">
                            @foreach ($apiErrors as $error)
                                <flux:callout.text>{{ $error }}</flux:callout.text>
                            @endforeach
                        </flux:callout>
                    @endif

                    <div class="flex flex-col-reverse gap-3 border-t border-zinc-100 pt-5 sm:flex-row sm:justify-end dark:border-zinc-800">
                        <flux:modal.close>
                            <flux:button variant="subtle">Cerrar</flux:button>
                        </flux:modal.close>
                        @if ($document->remote_id && ! $document->isAwaiting())
                            <flux:button icon="printer" target="_blank" :href="route('billing.documents.pdf', [$document, 'ticket'])">Ticket</flux:button>
                            <flux:button icon="document-arrow-down" target="_blank" :href="route('billing.documents.pdf', [$document, 'a4'])">PDF A4</flux:button>
                        @endif
                        @if ($document->isAwaiting())
                            <flux:button variant="primary" icon="arrow-path" wire:click="refresh">
                                <span wire:loading.remove wire:target="refresh">{{ $document->status === \App\Models\SunatDocument::STATUS_OFFLINE ? 'Reintentar envío' : 'Consultar estado' }}</span>
                                <span wire:loading wire:target="refresh">Consultando...</span>
                            </flux:button>
                        @endif
                        @if (($document->isTest() || $document->status === \App\Models\SunatDocument::STATUS_REJECTED) && ! $sale->isCancelled())
                            <flux:button :variant="$document->isAwaiting() ? 'outline' : 'primary'" icon="arrow-uturn-left" wire:click="startOver" data-test="invoice-again">
                                Volver a facturar
                            </flux:button>
                        @endif
                    </div>
                @else
                    {{-- Vista previa antes de enviar --}}
                    <form wire:submit="submit" class="flex flex-col gap-5">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <flux:input wire:model.live="issueDate" type="date" label="Fecha de emisión" required
                                :max="now('America/Lima')->toDateString()"
                                :min="now('America/Lima')->subDays(\App\Services\Billing\BillingService::MAX_DAYS_BACK)->toDateString()"
                                description="Hoy, o hasta {{ \App\Services\Billing\BillingService::MAX_DAYS_BACK }} días atrás (límite de SUNAT)." />

                            <flux:radio.group wire:model.live="documentType" label="Comprobante" variant="segmented">
                                <flux:radio value="boleta" label="Boleta {{ $settings->boleta_series }}" :disabled="! $settings->boleta_series" />
                                <flux:radio value="factura" label="Factura {{ $settings->factura_series }}" :disabled="! $settings->factura_series" />
                            </flux:radio.group>
                        </div>

                        <div class="rounded-2xl border border-zinc-200 p-4 text-sm dark:border-zinc-700">
                            <p class="text-xs text-zinc-500">Cliente</p>
                            @if ($customer)
                                <p class="font-medium">{{ $customer['razon_social'] }}</p>
                                <p class="text-zinc-500">{{ array_search($customer['tipo_documento'], \App\Services\Billing\BillingService::CUSTOMER_DOCUMENT_TYPES) }} {{ $customer['numero_documento'] }}</p>
                            @else
                                <p class="font-medium">CLIENTES VARIOS</p>
                                <p class="text-zinc-500">Sin documento (boletas hasta S/ {{ \App\Services\Billing\BillingService::BOLETA_ANONYMOUS_LIMIT }})</p>
                            @endif
                            <p class="mt-2 text-xs text-zinc-500">Para cambiar el cliente, edítelo en el detalle de la venta.</p>
                        </div>

                        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
                            <table class="w-full text-sm">
                                <thead class="bg-zinc-50 text-xs text-zinc-500 dark:bg-zinc-800/60">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-semibold">Descripción</th>
                                        <th class="px-3 py-2 text-right font-semibold">Cant.</th>
                                        <th class="px-3 py-2 text-right font-semibold">P. unit.</th>
                                        <th class="px-3 py-2 text-right font-semibold">Importe</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                    @foreach ($lines as $i => $line)
                                        <tr wire:key="invoice-line-{{ $i }}">
                                            <td class="px-3 py-2">{{ $line['descripcion'] }}</td>
                                            <td class="px-3 py-2 text-right tabular-nums">{{ $line['cantidad'] }}</td>
                                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $line['precio_unitario'], 2) }}</td>
                                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $line['importe'], 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="border-t border-zinc-200 dark:border-zinc-700">
                                    <tr>
                                        <td colspan="3" class="px-3 py-1.5 text-right text-zinc-500">Op. gravada</td>
                                        <td class="px-3 py-1.5 text-right tabular-nums">{{ number_format($sale->subtotal, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" class="px-3 py-1.5 text-right text-zinc-500">IGV (18%)</td>
                                        <td class="px-3 py-1.5 text-right tabular-nums">{{ number_format($sale->tax, 2) }}</td>
                                    </tr>
                                    <tr class="font-semibold">
                                        <td colspan="3" class="px-3 py-1.5 text-right">Total de la venta</td>
                                        <td class="px-3 py-1.5 text-right tabular-nums" data-test="invoice-total">S/ {{ number_format($sale->total, 2) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        @if ($problems || $apiErrors)
                            <flux:callout variant="danger" icon="exclamation-triangle">
                                @foreach ([...$problems, ...$apiErrors] as $problem)
                                    <flux:callout.text>{{ $problem }}</flux:callout.text>
                                @endforeach
                            </flux:callout>
                        @endif

                        <div class="flex flex-col-reverse gap-3 border-t border-zinc-100 pt-5 sm:flex-row sm:justify-end dark:border-zinc-800">
                            <flux:modal.close>
                                <flux:button variant="subtle">Cancelar</flux:button>
                            </flux:modal.close>
                            <flux:button type="submit" variant="primary" icon="paper-airplane" :disabled="(bool) $problems">
                                <span wire:loading.remove wire:target="submit">Enviar a SUNAT</span>
                                <span wire:loading wire:target="submit">Enviando...</span>
                            </flux:button>
                        </div>
                    </form>
                @endif
            </div>
        @endif
    </flux:modal>
</div>
