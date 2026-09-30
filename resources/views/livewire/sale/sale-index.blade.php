<div
    class="rounded-[2rem] border border-white/80 bg-white/70 p-5 shadow-xl shadow-slate-900/[0.04] backdrop-blur-xl sm:p-8 dark:border-slate-800 dark:bg-slate-900/75">

    <!-- Header -->
    <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div class="space-y-4">
            <flux:breadcrumbs>
                <flux:breadcrumbs.item :href="route('dashboard')" icon="home" />
                <flux:breadcrumbs.item :href="route('sale.sales.index')">Ventas</flux:breadcrumbs.item>
            </flux:breadcrumbs>

            <div>
                <flux:heading size="xl">Ventas</flux:heading>
                <flux:text class="mt-1.5">
                    Revisa, factura o anula las ventas registradas.
                </flux:text>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @can('ventas.registrar')
            <flux:button :href="route('sale.sales.create')" wire:navigate variant="primary" icon="plus">
                Nueva venta
            </flux:button>
            @endcan
            <flux:button icon="chart-bar" wire:click="$dispatch('open-category-summary')">
                Resumen anual
            </flux:button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="mt-7 flex flex-col gap-3 lg:flex-row lg:items-center">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
            placeholder="Buscar por código, comprobante o cliente..." />

        <flux:radio.group wire:model.live="status" variant="segmented">
            <flux:radio value="all">Todas</flux:radio>
            <flux:radio value="completed">Completadas</flux:radio>
            <flux:radio value="cancelled">Anuladas</flux:radio>
        </flux:radio.group>

        <flux:button wire:click="$toggle('onlyPendingInvoice')" :variant="$onlyPendingInvoice ? 'primary' : 'outline'"
            icon="document-text" class="shrink-0">
            Por facturar
            @if ($pendingInvoiceCount > 0)
                <flux:badge size="sm" color="amber" class="ml-1">{{ $pendingInvoiceCount }}</flux:badge>
            @endif
        </flux:button>
    </div>

    <!-- Tabla -->
    <div class="mt-5 overflow-x-auto rounded-2xl border border-zinc-200 dark:border-zinc-800">
        <table class="w-full min-w-[56rem] text-sm">
            <thead class="bg-zinc-50 text-xs text-zinc-500 dark:bg-zinc-800/60">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold">Código</th>
                    <th class="px-4 py-3 text-left font-semibold">Fecha</th>
                    <th class="px-4 py-3 text-left font-semibold">Cliente</th>
                    <th class="px-4 py-3 text-left font-semibold">Comprobante</th>
                    <th class="px-4 py-3 text-left font-semibold">Vendedor</th>
                    <th class="px-4 py-3 text-right font-semibold">Unid.</th>
                    <th class="px-4 py-3 text-right font-semibold">Total</th>
                    <th class="px-4 py-3 text-left font-semibold">Estado</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($sales as $sale)
                    <tr wire:key="sale-{{ $sale->id }}" wire:click="openSale({{ $sale->id }})"
                        @class([
                            'cursor-pointer transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/40',
                            'opacity-60' => $sale->isCancelled(),
                        ])>
                        <td class="px-4 py-3 font-medium text-zinc-900 dark:text-white">{{ $sale->code }}</td>
                        <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ $sale->sale_date?->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">
                            <p class="text-zinc-800 dark:text-zinc-200">{{ $sale->client?->display_name ?? 'Cliente varios' }}</p>
                            @if ($sale->client)
                                <p class="text-xs text-zinc-500">{{ $sale->client->document_number }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <p class="text-zinc-800 dark:text-zinc-200">{{ \App\Models\Sale::DOCUMENT_TYPES[$sale->document_type] ?? $sale->document_type }}</p>
                            <p class="text-xs text-zinc-500">{{ $sale->document_number ?? '—' }}</p>
                        </td>
                        <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ $sale->user?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ (int) $sale->units_count }}</td>
                        <td class="px-4 py-3 text-right font-semibold tabular-nums text-zinc-900 dark:text-white">
                            S/ {{ number_format($sale->total, 2) }}</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                @if ($sale->isCancelled())
                                    <flux:badge size="sm" color="red">Anulada</flux:badge>
                                @else
                                    <flux:badge size="sm" color="emerald">Completada</flux:badge>
                                    @if ($sale->has_invoice_pending && ! $sale->liveSunatDocument)
                                        <flux:badge size="sm" color="amber">Por facturar</flux:badge>
                                    @endif
                                @endif
                                @if ($doc = $sale->liveSunatDocument)
                                    <flux:badge size="sm" :color="$doc->statusColor()" title="{{ $doc->sunat_description }}">
                                        {{ $doc->fullNumber() }} · {{ $doc->statusLabel() }}
                                    </flux:badge>
                                    @if ($doc->credit_note_required)
                                        <flux:badge size="sm" color="orange">Emitir nota de crédito</flux:badge>
                                    @endif
                                @elseif ($test = $sale->latestTestSunatDocument)
                                    <flux:badge size="sm" color="zinc" title="Modo prueba: sin valor tributario">
                                        Prueba {{ $test->fullNumber() }} · {{ $test->statusLabel() }}
                                    </flux:badge>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if ($billingEnabled && ! $sale->isCancelled())
                                @can('facturacion.emitir')
                                    @if (! $sale->liveSunatDocument)
                                        <flux:button size="sm" icon="document-check" data-test="facturar-{{ $sale->id }}"
                                            wire:click.stop="$dispatch('open-invoice-sale', { id: {{ $sale->id }} })">
                                            Facturar
                                        </flux:button>
                                    @elseif ($sale->liveSunatDocument->isAwaiting())
                                        <flux:button size="sm" icon="arrow-path"
                                            wire:click.stop="$dispatch('open-invoice-sale', { id: {{ $sale->id }} })">
                                            Ver estado
                                        </flux:button>
                                    @endif
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-12 text-center text-zinc-500">
                            No se encontraron ventas con los filtros seleccionados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6 flex justify-end">
        {{ $sales->links() }}
    </div>

    <livewire:sale.sale-detail-modal />
    {{-- Botón "+" del cliente en el detalle de la venta (ej. para facturar a una empresa nueva). --}}
    <livewire:core.client-form-modal key="sale-detail-client-form" />
    <livewire:billing.invoice-sale-modal />
    <livewire:shared.category-year-summary type="sales" />
</div>
