<div
    class="rounded-[2rem] border border-white/80 bg-white/70 p-5 shadow-xl shadow-slate-900/[0.04] backdrop-blur-xl sm:p-8 dark:border-slate-800 dark:bg-slate-900/75">

    <!-- Header -->
    <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div class="space-y-4">
            <flux:breadcrumbs>
                <flux:breadcrumbs.item :href="route('dashboard')" icon="home" />
                <flux:breadcrumbs.item :href="route('purchasing.purchases.index')">Compras</flux:breadcrumbs.item>
            </flux:breadcrumbs>

            <div>
                <flux:heading size="xl">Compras</flux:heading>
                <flux:text class="mt-1.5">
                    Revisa las compras registradas y corrige sus datos o cantidades.
                </flux:text>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <flux:button :href="route('purchasing.purchases.create')" wire:navigate variant="primary" icon="plus">
                Registrar compra
            </flux:button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="mt-7 flex flex-col gap-3 lg:flex-row lg:items-center">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
            placeholder="Buscar por N° de compra, comprobante o proveedor..." />

        <flux:button wire:click="$toggle('onlyPendingInvoice')" :variant="$onlyPendingInvoice ? 'primary' : 'outline'"
            icon="document-text" class="shrink-0">
            Factura pendiente
            @if ($pendingInvoiceCount > 0)
                <flux:badge size="sm" color="amber" class="ml-1">{{ $pendingInvoiceCount }}</flux:badge>
            @endif
        </flux:button>
    </div>

    <!-- Tabla -->
    <div class="mt-5 overflow-x-auto rounded-2xl border border-zinc-200 dark:border-zinc-800">
        <table class="w-full min-w-[52rem] text-sm">
            <thead class="bg-zinc-50 text-xs text-zinc-500 dark:bg-zinc-800/60">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold">N°</th>
                    <th class="px-4 py-3 text-left font-semibold">Fecha</th>
                    <th class="px-4 py-3 text-left font-semibold">Proveedor</th>
                    <th class="px-4 py-3 text-left font-semibold">Comprobante</th>
                    <th class="px-4 py-3 text-left font-semibold">Registró</th>
                    <th class="px-4 py-3 text-right font-semibold">Unid.</th>
                    <th class="px-4 py-3 text-right font-semibold">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($purchases as $purchase)
                    <tr wire:key="purchase-{{ $purchase->id }}" wire:click="openPurchase({{ $purchase->id }})"
                        class="cursor-pointer transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/40">
                        <td class="px-4 py-3 font-medium text-zinc-900 dark:text-white">
                            #{{ $purchase->id }}
                            @if ($purchase->isDraft())
                                <flux:badge size="sm" color="zinc" class="ml-1">Borrador</flux:badge>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ $purchase->issue_date?->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">
                            <p class="text-zinc-800 dark:text-zinc-200">{{ $purchase->supplier?->display_name ?? 'Sin proveedor' }}</p>
                            @if ($purchase->supplier)
                                <p class="text-xs text-zinc-500">{{ $purchase->supplier->document_number }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <div>
                                    <p class="text-zinc-800 dark:text-zinc-200">{{ \App\Models\Sale::DOCUMENT_TYPES[$purchase->document_type] ?? $purchase->document_type }}</p>
                                    <p class="text-xs text-zinc-500">{{ $purchase->document_number ?? '—' }}</p>
                                </div>
                                @if ($purchase->has_invoice_pending)
                                    <flux:badge size="sm" color="amber">Factura pendiente</flux:badge>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ $purchase->user?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $purchase->isDraft() ? (int) collect($purchase->draft_items)->sum('quantity') : (int) $purchase->units_count }}</td>
                        <td class="px-4 py-3 text-right font-semibold tabular-nums text-zinc-900 dark:text-white">
                            S/ {{ number_format($purchase->total, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-zinc-500">
                            No se encontraron compras con los filtros seleccionados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6 flex justify-end">
        {{ $purchases->links() }}
    </div>

    <livewire:purchasing.purchase-detail-modal />
</div>
