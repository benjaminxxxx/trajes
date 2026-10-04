<div>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <div class="mb-2 flex items-center gap-2 text-xs font-semibold text-violet-600 dark:text-violet-400">
                <span class="grid size-6 place-items-center rounded-lg bg-violet-100 dark:bg-violet-950/60">
                    <flux:icon icon="banknotes" class="size-3.5 text-violet-600 dark:text-violet-400" />
                </span>
                Finanzas
            </div>
            <h1 class="text-2xl font-semibold text-zinc-900 dark:text-white">Gastos operativos</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                Los gastos no se editan ni se eliminan: si te equivocas, anúlalo (el monto vuelve a caja) y regístralo de nuevo.
            </p>
        </div>
        <div class="flex gap-2">
            <flux:button icon="chart-bar" wire:click="$dispatch('open-expense-summary')">Resumen anual</flux:button>
            <flux:button icon="plus" variant="primary" wire:click="$dispatch('open-expense-form')">Nuevo gasto</flux:button>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="mb-4 grid gap-3 sm:grid-cols-[1fr_170px_170px_auto] sm:items-end">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
            placeholder="Buscar por categoría o concepto" />
        <flux:input wire:model.live="dateFrom" type="date" label="Desde" />
        <flux:input wire:model.live="dateTo" type="date" label="Hasta" />
        <div class="rounded-xl bg-zinc-950 px-4 py-2 text-white dark:bg-zinc-800">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-violet-300">Total del período</p>
            <p class="text-lg font-bold">S/ {{ number_format($this->periodTotal, 2) }}</p>
        </div>
    </div>

    @if ($this->expenses->isEmpty())
        <div class="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-zinc-300 py-12 text-center dark:border-zinc-700">
            <flux:icon icon="banknotes" class="size-7 text-violet-500" />
            <span class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">No hay gastos en este período</span>
            <span class="text-xs text-zinc-500">Cambia las fechas del filtro o registra uno nuevo.</span>
        </div>
    @else
        <flux:table :paginate="$this->expenses">
            <flux:table.columns>
                <flux:table.column>Fecha</flux:table.column>
                <flux:table.column>Categoría</flux:table.column>
                <flux:table.column>Concepto</flux:table.column>
                <flux:table.column align="end">Monto</flux:table.column>
                <flux:table.column>Registró</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->expenses as $expense)
                    <flux:table.row :key="$expense->id">
                        <flux:table.cell class="whitespace-nowrap">{{ $expense->expense_date->format('d/m/Y H:i') }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" color="violet">{{ $expense->category }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <span @class(['block', 'text-zinc-400 line-through' => $expense->isCancelled()])>{{ $expense->concept }}</span>
                            @if ($expense->isCancelled())
                                <span class="block max-w-xs text-xs text-red-600 dark:text-red-400">
                                    Anulado el {{ $expense->cancelled_at->format('d/m/Y H:i') }} por {{ $expense->cancelledBy?->name ?? '—' }}: {{ $expense->cancellation_reason }}
                                </span>
                            @endif
                            @if ($expense->notes)
                                <span class="block max-w-xs truncate text-xs text-zinc-500" title="{{ $expense->notes }}">{{ $expense->notes }}</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end" @class(['whitespace-nowrap font-semibold', 'text-zinc-400 line-through' => $expense->isCancelled()])>S/ {{ number_format($expense->amount, 2) }}</flux:table.cell>
                        <flux:table.cell class="text-xs text-zinc-500">{{ $expense->user?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell align="end">
                            @if ($expense->isCancelled())
                                <flux:badge size="sm" color="red">Anulado</flux:badge>
                            @else
                                <flux:button size="sm" variant="ghost" icon="arrow-uturn-left" wire:click="confirmCancel({{ $expense->id }})"
                                    title="Anular gasto" aria-label="Anular gasto" />
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    {{-- Anulación: el gasto se conserva y su monto vuelve a caja --}}
    <flux:modal wire:model="showCancelModal" class="max-w-md">
        @if ($this->pendingCancellation)
            <form wire:submit="cancel" class="flex flex-col gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">Anular gasto</h2>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                        El gasto no se borra: queda anulado y su monto vuelve a caja como reingreso.
                    </p>
                </div>

                <div class="rounded-xl border border-zinc-200 p-3 text-sm dark:border-zinc-700">
                    <p class="text-xs font-semibold text-violet-600">{{ $this->pendingCancellation->category }}</p>
                    <p class="font-medium text-zinc-900 dark:text-white">{{ $this->pendingCancellation->concept }}</p>
                    <p class="mt-1 text-zinc-500">
                        S/ {{ number_format($this->pendingCancellation->amount, 2) }} · {{ $this->pendingCancellation->expense_date->format('d/m/Y H:i') }}
                    </p>
                </div>

                <flux:input wire:model="cancellationReason" label="Motivo" placeholder="Ej: registrado por error, era una prueba" required />

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="subtle">Cancelar</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="danger">
                        <span wire:loading.remove wire:target="cancel">Anular gasto</span>
                        <span wire:loading wire:target="cancel">Anulando...</span>
                    </flux:button>
                </div>
            </form>
        @endif
    </flux:modal>

    <livewire:finance.expense-form />
    <livewire:finance.expense-year-summary />
</div>