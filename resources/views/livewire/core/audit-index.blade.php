<div>
    <div class="mb-6">
        <div class="mb-2 flex items-center gap-2 text-xs font-semibold text-violet-600 dark:text-violet-400">
            <span class="grid size-6 place-items-center rounded-lg bg-violet-100 dark:bg-violet-950/60">
                <flux:icon icon="shield-check" class="size-3.5 text-violet-600 dark:text-violet-400" />
            </span>
            Administración
        </div>
        <h1 class="text-2xl font-semibold text-zinc-900 dark:text-white">Auditoría</h1>
        <p class="mt-1 max-w-2xl text-sm text-zinc-500 dark:text-zinc-400">
            Cada edición y eliminación queda aquí: quién la hizo, cuándo y qué valores había antes y después.
            Los movimientos de dinero se ven en Caja.
        </p>
    </div>

    <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5 lg:items-end">
        <flux:select wire:model.live="event" label="Acción">
            <flux:select.option value="">Todas</flux:select.option>
            @foreach (\App\Models\AuditLog::EVENTS as $value => $label)
                <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="modelType" label="Qué">
            <flux:select.option value="">Todo</flux:select.option>
            @foreach (\App\Models\AuditLog::MODEL_LABELS as $class => $label)
                <flux:select.option value="{{ $class }}">{{ $label }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="userId" label="Quién">
            <flux:select.option value="">Todos</flux:select.option>
            @foreach ($this->users as $id => $name)
                <flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:input wire:model.live="dateFrom" type="date" label="Desde" />
        <flux:input wire:model.live="dateTo" type="date" label="Hasta" />
    </div>

    @if ($this->logs->isEmpty())
        <div class="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-zinc-300 py-12 text-center dark:border-zinc-700">
            <flux:icon icon="shield-check" class="size-7 text-violet-500" />
            <span class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">Sin registros</span>
            <span class="text-xs text-zinc-500">Todavía no hay ediciones ni eliminaciones con estos filtros.</span>
        </div>
    @else
        <flux:table :paginate="$this->logs">
            <flux:table.columns>
                <flux:table.column>Fecha</flux:table.column>
                <flux:table.column>Acción</flux:table.column>
                <flux:table.column>Qué</flux:table.column>
                <flux:table.column>Cambios</flux:table.column>
                <flux:table.column>Quién</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->logs as $log)
                    @php($changes = $log->changes())
                    <flux:table.row :key="$log->id">
                        <flux:table.cell class="whitespace-nowrap">{{ $log->created_at->format('d/m/Y H:i') }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$log->event === 'deleted' ? 'red' : 'amber'">{{ $log->eventLabel() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap">
                            {{ $log->modelLabel() }} <span class="text-xs text-zinc-500">#{{ $log->auditable_id }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="block max-w-md truncate text-xs text-zinc-600 dark:text-zinc-300">
                                @if ($log->event === 'deleted')
                                    {{ $log->old_values['name'] ?? $log->old_values['concept'] ?? $log->old_values['code'] ?? count($changes).' campos' }}
                                @else
                                    {{ collect(array_keys($changes))->map(fn ($field) => \App\Livewire\Core\AuditIndex::fieldLabel($field))->join(', ') }}
                                @endif
                            </span>
                        </flux:table.cell>
                        <flux:table.cell class="text-xs text-zinc-500">{{ $log->user_name ?? 'Sistema' }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:button size="sm" variant="ghost" icon="eye" wire:click="show({{ $log->id }})" aria-label="Ver detalle" />
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal wire:model="showDetail" class="w-full max-w-2xl">
        @if ($this->detail)
            @php($log = $this->detail)
            <div class="flex flex-col gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">
                        {{ $log->eventLabel() }} · {{ $log->modelLabel() }} #{{ $log->auditable_id }}
                    </h2>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                        {{ $log->user_name ?? 'Sistema' }} · {{ $log->created_at->format('d/m/Y H:i:s') }}
                    </p>
                </div>

                <div class="max-h-[60vh] overflow-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 bg-zinc-50 text-xs text-zinc-500 dark:bg-zinc-800">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold">Campo</th>
                                <th class="px-3 py-2 text-left font-semibold">{{ $log->event === 'deleted' ? 'Valor que tenía' : 'Antes' }}</th>
                                @if ($log->event !== 'deleted')
                                    <th class="px-3 py-2 text-left font-semibold">Después</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($log->changes() as $field => $change)
                                <tr wire:key="audit-field-{{ $log->id }}-{{ $field }}">
                                    <td class="px-3 py-2 font-medium text-zinc-700 dark:text-zinc-300">{{ \App\Livewire\Core\AuditIndex::fieldLabel($field) }}</td>
                                    <td class="break-all px-3 py-2 text-zinc-600 dark:text-zinc-400">{{ \App\Livewire\Core\AuditIndex::displayValue($change['old']) }}</td>
                                    @if ($log->event !== 'deleted')
                                        <td class="break-all px-3 py-2 text-zinc-900 dark:text-white">{{ \App\Livewire\Core\AuditIndex::displayValue($change['new']) }}</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end">
                    <flux:modal.close>
                        <flux:button variant="subtle">Cerrar</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
