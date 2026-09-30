<div
    class="rounded-[2rem] border border-white/80 bg-white/70 p-5 shadow-xl shadow-slate-900/[0.04] backdrop-blur-xl sm:p-8 dark:border-slate-800 dark:bg-slate-900/75">

    <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div class="space-y-4">
            <flux:breadcrumbs>
                <flux:breadcrumbs.item :href="route('dashboard')" icon="home" />
                <flux:breadcrumbs.item>Facturación</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>Comprobantes electrónicos</flux:breadcrumbs.item>
            </flux:breadcrumbs>
            <div>
                <flux:heading size="xl">Comprobantes electrónicos</flux:heading>
                <flux:text class="mt-1.5">Facturas y boletas enviadas a SUNAT desde las ventas.</flux:text>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($awaitingCount > 0)
                <flux:button icon="arrow-path" wire:click="refreshAll">
                    <span wire:loading.remove wire:target="refreshAll">Consultar pendientes ({{ $awaitingCount }})</span>
                    <span wire:loading wire:target="refreshAll">Consultando...</span>
                </flux:button>
            @endif
            <flux:button :href="route('sale.sales.index')" wire:navigate icon="shopping-cart">Ir a ventas</flux:button>
        </div>
    </div>

    @if ($settings->status === \App\Models\BillingSetting::STATUS_INVALID_TOKEN)
        <flux:callout variant="danger" icon="no-symbol" class="mt-6" heading="El token de facturación no existe o fue revocado">
            <flux:callout.text>
                No se pueden emitir ni consultar comprobantes.
                @can('facturacion.configurar')
                    <flux:link :href="route('billing.settings')" wire:navigate>Actualizar el token</flux:link>
                @endcan
            </flux:callout.text>
        </flux:callout>
    @elseif (! $settings->isReady())
        <flux:callout icon="information-circle" class="mt-6" heading="Facturación electrónica sin configurar">
            <flux:callout.text>
                Conecte su empresa de Kipusoft Facturación para facturar ventas.
                @can('facturacion.configurar')
                    <flux:link :href="route('billing.settings')" wire:navigate>Configurar</flux:link>
                @endcan
            </flux:callout.text>
        </flux:callout>
    @endif

    @if ($creditNoteCount > 0)
        <flux:callout variant="warning" icon="exclamation-triangle" class="mt-6" data-test="credit-note-warning"
            heading="{{ $creditNoteCount }} {{ $creditNoteCount === 1 ? 'comprobante requiere' : 'comprobantes requieren' }} nota de crédito">
            <flux:callout.text>
                Son ventas anuladas cuya factura o boleta ya existe en SUNAT. Emita la nota de crédito para cada una
                (aún no se genera desde el sistema).
                <flux:link wire:click="$set('filter', 'nota_credito')" class="cursor-pointer">Ver cuáles</flux:link>
            </flux:callout.text>
        </flux:callout>
    @endif

    <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center">
        <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" placeholder="Buscar por número, venta o cliente" class="sm:max-w-sm" />
        <flux:radio.group wire:model.live="filter" variant="segmented" size="sm">
            <flux:radio value="todos" label="Todos" />
            <flux:radio value="pendientes" label="Sin respuesta" />
            <flux:radio value="rechazados" label="Rechazados" />
            <flux:radio value="nota_credito" label="Requieren N.C." />
        </flux:radio.group>
    </div>

    <div class="mt-4 overflow-x-auto rounded-2xl border border-zinc-200 dark:border-zinc-800">
        <table class="w-full text-sm">
            <thead class="bg-zinc-50 text-xs text-zinc-500 dark:bg-zinc-800/60">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold">Comprobante</th>
                    <th class="px-4 py-3 text-left font-semibold">Emisión</th>
                    <th class="px-4 py-3 text-left font-semibold">Venta</th>
                    <th class="px-4 py-3 text-left font-semibold">Cliente</th>
                    <th class="px-4 py-3 text-right font-semibold">Total</th>
                    <th class="px-4 py-3 text-left font-semibold">Estado SUNAT</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($documents as $document)
                    <tr wire:key="sunat-document-{{ $document->id }}">
                        <td class="px-4 py-3">
                            <p class="font-medium text-zinc-900 dark:text-white">{{ $document->fullNumber() }}</p>
                            <p class="text-xs text-zinc-500">{{ $document->typeLabel() }}{{ $document->environment === 'beta' ? ' · prueba' : '' }}</p>
                        </td>
                        <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ $document->issue_date?->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">
                            <p>{{ $document->sale?->code }}</p>
                            @if ($document->sale?->isCancelled())
                                <flux:badge size="sm" color="red">Anulada</flux:badge>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <p class="text-zinc-800 dark:text-zinc-200">{{ $document->customer_name }}</p>
                            <p class="text-xs text-zinc-500">{{ $document->customer_document_number }}</p>
                        </td>
                        <td class="px-4 py-3 text-right font-semibold tabular-nums">S/ {{ number_format($document->total, 2) }}</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                <flux:badge size="sm" :color="$document->statusColor()" title="{{ $document->sunat_description }}">{{ $document->statusLabel() }}</flux:badge>
                                @if ($document->credit_note_required)
                                    <flux:badge size="sm" color="orange">Emitir nota de crédito</flux:badge>
                                @endif
                            </div>
                            @if ($document->status === \App\Models\SunatDocument::STATUS_REJECTED)
                                <p class="mt-1 max-w-xs text-xs text-red-600 dark:text-red-400">{{ \Illuminate\Support\Str::limit($document->sunat_description, 120) }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            @if ($document->isAwaiting())
                                <flux:button size="sm" icon="arrow-path" wire:click="refresh({{ $document->id }})">
                                    {{ $document->status === \App\Models\SunatDocument::STATUS_OFFLINE ? 'Reintentar' : 'Consultar' }}
                                </flux:button>
                            @elseif ($document->remote_id)
                                <flux:button size="sm" variant="ghost" target="_blank" :href="route('billing.documents.pdf', [$document, 'a4'])">PDF</flux:button>
                                <flux:button size="sm" variant="ghost" target="_blank" :href="route('billing.documents.pdf', [$document, 'ticket'])">Ticket</flux:button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-zinc-500">Todavía no hay comprobantes electrónicos.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6 flex justify-end">
        {{ $documents->links() }}
    </div>
</div>
