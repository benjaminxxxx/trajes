<div
    class="rounded-[2rem] border border-white/80 bg-white/70 p-5 shadow-xl shadow-slate-900/[0.04] backdrop-blur-xl sm:p-8 dark:border-slate-800 dark:bg-slate-900/75">

    <!-- Header -->
    <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div class="space-y-4">
            <flux:breadcrumbs>
                <flux:breadcrumbs.item :href="route('dashboard')" icon="home" />
                <flux:breadcrumbs.item :href="route('rental.rentals.index')">Alquileres</flux:breadcrumbs.item>
            </flux:breadcrumbs>

            <div>
                <flux:heading size="xl">Alquileres</flux:heading>
                <flux:text class="mt-1.5">
                    Sigue las prendas que están fuera, edita o anula alquileres.
                </flux:text>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @can('alquileres.registrar')
            <flux:button :href="route('rental.rentals.create')" wire:navigate variant="primary" icon="plus">
                Nuevo alquiler
            </flux:button>
            @endcan
            <flux:button icon="bookmark" wire:click="$dispatch('open-reservations')">
                Separadas
                @if ($reservedCount > 0)
                    <flux:badge size="sm" color="amber" class="ml-1">{{ $reservedCount }}</flux:badge>
                @endif
            </flux:button>
            <flux:button icon="chart-bar" wire:click="$dispatch('open-category-summary')">
                Resumen anual
            </flux:button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="mt-7 flex flex-col gap-3 lg:flex-row lg:items-center">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
            placeholder="Buscar por código o cliente..." />

        <flux:radio.group wire:model.live="status" variant="segmented">
            <flux:radio value="all">Todos</flux:radio>
            <flux:radio value="out">En curso</flux:radio>
            <flux:radio value="overdue">Vencidos{{ $overdueCount > 0 ? " ({$overdueCount})" : '' }}</flux:radio>
            <flux:radio value="returned">Devueltos</flux:radio>
            <flux:radio value="cancelled">Anulados</flux:radio>
        </flux:radio.group>
    </div>

    <!-- Tabla -->
    <div class="mt-5 overflow-x-auto rounded-2xl border border-zinc-200 dark:border-zinc-800">
        <table class="w-full min-w-[56rem] text-sm">
            <thead class="bg-zinc-50 text-xs text-zinc-500 dark:bg-zinc-800/60">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold">Código</th>
                    <th class="px-4 py-3 text-left font-semibold">Cliente</th>
                    <th class="px-4 py-3 text-left font-semibold">Salida</th>
                    <th class="px-4 py-3 text-left font-semibold">Devolución</th>
                    <th class="px-4 py-3 text-right font-semibold">Prendas</th>
                    <th class="px-4 py-3 text-right font-semibold">Total</th>
                    <th class="px-4 py-3 text-right font-semibold">Garantía</th>
                    <th class="px-4 py-3 text-left font-semibold">Estado</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($rentals as $rental)
                    <tr wire:key="rental-{{ $rental->id }}" wire:click="openRental({{ $rental->id }})"
                        @class([
                            'cursor-pointer transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/40',
                            'opacity-60' => $rental->isCancelled(),
                        ])>
                        <td class="px-4 py-3 font-medium text-zinc-900 dark:text-white">{{ $rental->code }}</td>
                        <td class="px-4 py-3">
                            <p class="text-zinc-800 dark:text-zinc-200">{{ $rental->client?->display_name }}</p>
                            <p class="text-xs text-zinc-500">{{ $rental->client?->document_number }}</p>
                        </td>
                        <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ $rental->pickup_date?->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3">
                            <p @class([
                                'text-zinc-600 dark:text-zinc-300',
                                'font-semibold text-red-600 dark:text-red-400' => $rental->isOverdue(),
                            ])>
                                {{ $rental->expected_return_date?->format('d/m/Y H:i') }}
                            </p>
                            @if ($rental->actual_return_date)
                                <p class="text-xs text-zinc-500">Devuelto {{ $rental->actual_return_date->format('d/m/Y') }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $rental->details_count }}</td>
                        <td class="px-4 py-3 text-right font-semibold tabular-nums text-zinc-900 dark:text-white">
                            S/ {{ number_format($rental->rental_total, 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-zinc-600 dark:text-zinc-300">
                            {{ number_format($rental->deposit_amount, 2) }}</td>
                        <td class="px-4 py-3">
                            @include('livewire.rental.partials.status-badge', ['rental' => $rental])
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-12 text-center text-zinc-500">
                            No se encontraron alquileres con los filtros seleccionados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6 flex justify-end">
        {{ $rentals->links() }}
    </div>

    <livewire:rental.rental-detail-modal />
    <livewire:shared.category-year-summary type="rentals" />
    <livewire:rental.reservations-modal />
</div>
