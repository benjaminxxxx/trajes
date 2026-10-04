<div>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <div class="mb-2 flex items-center gap-2 text-xs font-semibold text-violet-600 dark:text-violet-400">
                <span class="grid size-6 place-items-center rounded-lg bg-violet-100 dark:bg-violet-950/60">
                    <flux:icon icon="banknotes" class="size-3.5 text-violet-600 dark:text-violet-400" />
                </span>
                Finanzas
            </div>
            <h1 class="text-2xl font-semibold text-zinc-900 dark:text-white">Caja</h1>
            <p class="mt-1 max-w-2xl text-sm text-zinc-500 dark:text-zinc-400">
                Todo movimiento de dinero pasa por aquí y el saldo debe coincidir con tu cuenta bancaria.
                La historia no se borra: un error se corrige con el movimiento inverso.
            </p>
        </div>
        @can('caja.registrar')
            <div class="flex flex-wrap gap-2">
                <flux:button icon="arrow-up-tray" wire:click="openForm('{{ \App\Models\CashMovement::ORIGIN_WITHDRAWAL }}')">Retirar</flux:button>
                <flux:button icon="arrow-down-tray" variant="primary" wire:click="openForm('{{ \App\Models\CashMovement::ORIGIN_CAPITAL_INJECTION }}')">Ingresar dinero</flux:button>
            </div>
        @endcan
    </div>

    {{-- Saldo y cuadre con el banco --}}
    <div class="mb-5 grid gap-3 lg:grid-cols-[minmax(0,280px)_1fr]">
        <div class="rounded-2xl bg-zinc-950 p-5 text-white dark:bg-zinc-800">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-violet-300">Saldo en caja</p>
            <p @class(['mt-1 text-3xl font-bold tabular-nums', 'text-red-400' => $this->balance < 0]) data-test="cash-balance">
                S/ {{ number_format($this->balance, 2) }}
            </p>
            @if ($this->balance < 0)
                <p class="mt-1 text-xs text-red-300">La caja está en negativo: se gastó más de lo que se ingresó.</p>
            @endif
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="grid gap-3 sm:grid-cols-[200px_1fr] sm:items-end">
                <flux:input wire:model.live.debounce.400ms="bankBalance" type="number" step="0.01" inputmode="decimal"
                    label="¿Cuánto hay en el banco?" placeholder="Saldo real de la cuenta" />
                <div class="text-sm" data-test="cash-reconcile">
                    @if ($this->bankDifference === null)
                        <p class="text-zinc-500">Escribe el saldo de tu cuenta para comprobar que cuadra con la caja.</p>
                    @elseif (abs($this->bankDifference) < 0.01)
                        <p class="font-medium text-emerald-600 dark:text-emerald-400">Cuadra: el banco y la caja coinciden.</p>
                    @elseif ($this->bankDifference < 0)
                        <p class="font-medium text-red-600 dark:text-red-400">
                            Faltan S/ {{ number_format(abs($this->bankDifference), 2) }} en el banco.
                        </p>
                        <p class="text-xs text-zinc-500">
                            Deposita esa diferencia en la cuenta, o si fue una salida real que no se registró, regístrala como retiro o egreso.
                        </p>
                    @else
                        <p class="font-medium text-amber-600 dark:text-amber-400">
                            Sobran S/ {{ number_format($this->bankDifference, 2) }} en el banco.
                        </p>
                        <p class="text-xs text-zinc-500">
                            Es dinero tuyo prestado a la caja: retíralo de la cuenta para igualar, o regístralo como inyección de capital si se queda en el negocio.
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-[160px_220px_170px_170px_1fr] lg:items-end">
        <flux:select wire:model.live="type" label="Tipo">
            <flux:select.option value="">Todos</flux:select.option>
            <flux:select.option value="income">Ingresos</flux:select.option>
            <flux:select.option value="expense">Egresos</flux:select.option>
        </flux:select>
        <flux:select wire:model.live="origin" label="Concepto">
            <flux:select.option value="">Todos</flux:select.option>
            @foreach (\App\Models\CashMovement::ORIGINS as $value => $label)
                <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:input wire:model.live="dateFrom" type="date" label="Desde" />
        <flux:input wire:model.live="dateTo" type="date" label="Hasta" />
        <div class="flex gap-4 text-sm lg:justify-end">
            <span class="text-emerald-600 dark:text-emerald-400">Ingresos: <b class="tabular-nums">S/ {{ number_format($this->totals['income'], 2) }}</b></span>
            <span class="text-red-600 dark:text-red-400">Egresos: <b class="tabular-nums">S/ {{ number_format($this->totals['expense'], 2) }}</b></span>
        </div>
    </div>

    @if ($this->movements->isEmpty())
        <div class="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-zinc-300 py-12 text-center dark:border-zinc-700">
            <flux:icon icon="banknotes" class="size-7 text-violet-500" />
            <span class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">Sin movimientos</span>
            <span class="text-xs text-zinc-500">Empieza registrando el dinero con el que abre la caja (inyección de capital).</span>
        </div>
    @else
        <flux:table :paginate="$this->movements">
            <flux:table.columns>
                <flux:table.column>Fecha</flux:table.column>
                <flux:table.column>Concepto</flux:table.column>
                <flux:table.column>Detalle</flux:table.column>
                <flux:table.column align="end">Ingreso</flux:table.column>
                <flux:table.column align="end">Egreso</flux:table.column>
                <flux:table.column>Registró</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->movements as $movement)
                    <flux:table.row :key="$movement->id">
                        <flux:table.cell class="whitespace-nowrap">{{ $movement->created_at->format('d/m/Y H:i') }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$movement->isManual() ? 'violet' : 'zinc'">{{ $movement->originLabel() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="block max-w-md truncate" title="{{ $movement->description }}">{{ $movement->description }}</span>
                            @if ($movement->reverses_id)
                                <span class="block text-xs text-zinc-500">Inverso del movimiento #{{ $movement->reverses_id }}</span>
                            @elseif ($movement->reversal)
                                <span class="block text-xs text-red-600 dark:text-red-400">Anulado el {{ $movement->reversal->created_at->format('d/m/Y H:i') }}</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end" class="whitespace-nowrap font-semibold tabular-nums text-emerald-600 dark:text-emerald-400">
                            {{ $movement->isIncome() ? 'S/ '.number_format($movement->amount, 2) : '' }}
                        </flux:table.cell>
                        <flux:table.cell align="end" class="whitespace-nowrap font-semibold tabular-nums text-red-600 dark:text-red-400">
                            {{ $movement->isIncome() ? '' : 'S/ '.number_format($movement->amount, 2) }}
                        </flux:table.cell>
                        <flux:table.cell class="text-xs text-zinc-500">{{ $movement->user?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell align="end">
                            @can('caja.registrar')
                                @if ($movement->isManual() && ! $movement->reverses_id && ! $movement->reversal)
                                    <flux:button size="sm" variant="ghost" icon="arrow-uturn-left" wire:click="confirmReverse({{ $movement->id }})"
                                        title="Anular con el movimiento inverso" aria-label="Anular con el movimiento inverso" />
                                @endif
                            @endcan
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    {{-- Movimiento manual --}}
    <flux:modal wire:model="showForm" class="w-full max-w-md">
        <form wire:submit="save" class="flex flex-col gap-4">
            <div>
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">Movimiento de caja</h2>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                    Ventas, alquileres, compras y gastos se registran solos desde su módulo.
                </p>
            </div>

            <flux:select wire:model="movementOrigin" label="Tipo de movimiento">
                <flux:select.option value="capital_injection">Inyección de capital (entra)</flux:select.option>
                <flux:select.option value="other_income">Otro ingreso (entra)</flux:select.option>
                <flux:select.option value="withdrawal">Retiro de caja (sale)</flux:select.option>
                <flux:select.option value="other_expense">Otro egreso (sale)</flux:select.option>
            </flux:select>
            <flux:input wire:model="amount" type="number" step="0.01" min="0.01" inputmode="decimal" label="Monto (S/)" required />
            <flux:input wire:model="description" label="Motivo" placeholder="Ej: capital para la campaña de diciembre" required />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="subtle">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">
                    <span wire:loading.remove wire:target="save">Registrar</span>
                    <span wire:loading wire:target="save">Registrando...</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Efecto inverso --}}
    <flux:modal wire:model="showReverse" class="w-full max-w-md">
        @if ($this->pendingReverse)
            <form wire:submit="reverse" class="flex flex-col gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">Anular movimiento</h2>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                        El movimiento original se conserva y se registra uno inverso por el mismo monto.
                    </p>
                </div>

                <div class="rounded-xl border border-zinc-200 p-3 text-sm dark:border-zinc-700">
                    <p class="text-xs font-semibold text-violet-600">{{ $this->pendingReverse->originLabel() }}</p>
                    <p class="font-medium text-zinc-900 dark:text-white">{{ $this->pendingReverse->description }}</p>
                    <p class="mt-1 text-zinc-500">
                        {{ $this->pendingReverse->isIncome() ? 'Ingreso' : 'Egreso' }} de S/ {{ number_format($this->pendingReverse->amount, 2) }}
                        · {{ $this->pendingReverse->created_at->format('d/m/Y H:i') }}
                    </p>
                </div>

                <flux:input wire:model="reverseReason" label="Motivo" placeholder="Ej: monto equivocado, era una prueba" required />

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="subtle">Cancelar</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="danger">
                        <span wire:loading.remove wire:target="reverse">Registrar inverso</span>
                        <span wire:loading wire:target="reverse">Registrando...</span>
                    </flux:button>
                </div>
            </form>
        @endif
    </flux:modal>
</div>
