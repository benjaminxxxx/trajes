<div
    class="rounded-[2rem] border border-white/80 bg-white/70 p-5 shadow-xl shadow-slate-900/[0.04] backdrop-blur-xl sm:p-8 dark:border-slate-800 dark:bg-slate-900/75">

    <!-- Header -->
    <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div class="space-y-4">
            <flux:breadcrumbs>
                <flux:breadcrumbs.item icon="globe-alt">Plataforma</flux:breadcrumbs.item>
                <flux:breadcrumbs.item :href="route('platform.accounts.index')">Negocios</flux:breadcrumbs.item>
            </flux:breadcrumbs>

            <div>
                <flux:heading size="xl">Negocios en Kipusoft Alquiler</flux:heading>
                <flux:text class="mt-1.5">Todos los negocios que usan el sistema, su dueño, su estado y cuánto lo usan.</flux:text>
            </div>
        </div>

        <flux:button wire:click="create" variant="primary" icon="plus">Registrar negocio de un cliente</flux:button>
    </div>

    <!-- Resumen -->
    <div class="mt-7 grid gap-3 sm:grid-cols-4">
        <div class="rounded-2xl bg-zinc-50 p-4 dark:bg-zinc-800/60">
            <p class="text-xs text-zinc-500">Negocios</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums">{{ $statusCounts->sum() }}</p>
        </div>
        @foreach ([
            'active' => 'text-emerald-600 dark:text-emerald-400',
            'trial' => 'text-sky-600 dark:text-sky-400',
            'blocked' => 'text-red-600 dark:text-red-400',
        ] as $key => $colorClasses)
            <div class="rounded-2xl bg-zinc-50 p-4 dark:bg-zinc-800/60">
                <p class="text-xs text-zinc-500">{{ $statuses[$key] }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums {{ $colorClasses }}">{{ $statusCounts[$key] ?? 0 }}</p>
            </div>
        @endforeach
    </div>

    <!-- Filtros -->
    <div class="mt-5 flex flex-col gap-3 lg:flex-row lg:items-center">
        <flux:radio.group wire:model.live="status" variant="segmented">
            <flux:radio value="todos">Todos</flux:radio>
            @foreach ($statuses as $value => $label)
                <flux:radio :value="$value">{{ $label }}</flux:radio>
            @endforeach
        </flux:radio.group>

        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Buscar por negocio, RUC o correo del dueño..." />
    </div>

    <!-- Tabla -->
    <div class="mt-5 overflow-x-auto rounded-2xl border border-zinc-200 dark:border-zinc-800">
        <table class="w-full min-w-[56rem] text-sm">
            <thead class="bg-zinc-50 text-xs text-zinc-500 dark:bg-zinc-800/60">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold">Negocio</th>
                    <th class="px-4 py-3 text-left font-semibold">Dueño</th>
                    <th class="px-4 py-3 text-left font-semibold">Estado</th>
                    <th class="px-4 py-3 text-right font-semibold">Personas</th>
                    <th class="px-4 py-3 text-right font-semibold">Productos</th>
                    <th class="px-4 py-3 text-right font-semibold">Ventas</th>
                    <th class="px-4 py-3 text-right font-semibold">Alquileres</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($accounts as $account)
                    <tr wire:key="account-{{ $account->id }}">
                        <td class="px-4 py-3">
                            <p class="font-medium text-zinc-900 dark:text-white">{{ $account->name }}</p>
                            <p class="text-xs text-zinc-500">
                                {{ $account->ruc ? 'RUC '.$account->ruc.' · ' : '' }}desde {{ $account->created_at?->format('d/m/Y') }}
                            </p>
                        </td>
                        <td class="px-4 py-3">
                            <p class="text-zinc-800 dark:text-zinc-200">{{ $account->owner?->name }}</p>
                            <p class="text-xs text-zinc-500">{{ $account->owner?->email }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <flux:badge size="sm" :color="['active' => 'emerald', 'trial' => 'sky', 'suspended' => 'amber', 'blocked' => 'red'][$account->status] ?? 'zinc'">
                                {{ $statuses[$account->status] ?? $account->status }}
                            </flux:badge>
                            @if ($account->status === 'trial' && $account->trial_ends_at)
                                <p class="mt-1 text-[11px] text-zinc-500">hasta {{ $account->trial_ends_at->format('d/m/Y') }}</p>
                            @elseif ($account->isBlocked() && $account->blocked_reason)
                                <p class="mt-1 text-[11px] text-red-500">{{ $account->blocked_reason }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $account->members_count }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $usage['products'][$account->id] ?? 0 }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $usage['sales'][$account->id] ?? 0 }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $usage['rentals'][$account->id] ?? 0 }}</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-1">
                                <flux:button size="sm" variant="ghost" icon="pencil" wire:click="edit({{ $account->id }})" title="Editar datos" aria-label="Editar datos" />
                                <flux:button size="sm" variant="ghost" icon="star" wire:click="openOwner({{ $account->id }})" title="Cambiar dueño" aria-label="Cambiar dueño" />
                                @if ($account->isBlocked())
                                    <flux:button size="sm" variant="ghost" icon="lock-open" wire:click="unblock({{ $account->id }})"
                                        wire:confirm="¿Desbloquear «{{ $account->name }}»?" title="Desbloquear" aria-label="Desbloquear" />
                                @else
                                    <flux:button size="sm" variant="ghost" icon="lock-closed" wire:click="openBlock({{ $account->id }})" title="Bloquear" aria-label="Bloquear" />
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-12 text-center text-zinc-500">No hay negocios con esos filtros.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Registrar / editar negocio -->
    <flux:modal wire:model="showForm" class="w-full max-w-lg">
        <form wire:submit="save" class="flex flex-col gap-5">
            <flux:heading size="lg">{{ $accountId ? 'Datos del negocio' : 'Registrar negocio de un cliente' }}</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="name" label="Nombre del negocio" placeholder="Glamour & Estilos" required class="sm:col-span-2" />
                <flux:input wire:model="ruc" label="RUC" maxlength="11" />
                <flux:input wire:model="phone" label="Teléfono" />
                <flux:input wire:model="address" label="Dirección" class="sm:col-span-2" />
            </div>

            @unless ($accountId)
                <div class="grid gap-4 border-t border-zinc-100 pt-4 sm:grid-cols-2 dark:border-zinc-800">
                    <flux:input wire:model="ownerName" label="Nombre del dueño" required />
                    <flux:input wire:model="ownerEmail" type="email" label="Correo del dueño" required />
                    <flux:text class="text-xs sm:col-span-2">
                        Podrá entrar con Google usando ese correo, o con el enlace para crear su contraseña que le llegará por correo.
                    </flux:text>
                </div>
            @endunless

            <div class="flex justify-end gap-2 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                <flux:modal.close>
                    <flux:button variant="subtle">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Guardar</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Cambiar dueño -->
    <flux:modal wire:model="showOwner" class="w-full max-w-md">
        <form wire:submit="changeOwner" class="flex flex-col gap-5">
            <div>
                <flux:heading size="lg">Cambiar dueño</flux:heading>
                <flux:text class="mt-1">
                    {{ $selected?->name }} · dueño actual: {{ $selected?->owner?->email }}.
                    El nuevo dueño queda como administrador; el anterior sigue como administrador.
                </flux:text>
            </div>
            <flux:input wire:model="ownerName" label="Nombre" required />
            <flux:input wire:model="ownerEmail" type="email" label="Correo" required />
            <div class="flex justify-end gap-2 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                <flux:modal.close>
                    <flux:button variant="subtle">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Cambiar dueño</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Bloquear -->
    <flux:modal wire:model="showBlock" class="w-full max-w-md">
        <form wire:submit="block" class="flex flex-col gap-5">
            <div>
                <flux:heading size="lg">Bloquear negocio</flux:heading>
                <flux:text class="mt-1">{{ $selected?->name }}: nadie de este negocio podrá entrar, ni sus vendedores. Sus datos se conservan.</flux:text>
            </div>
            <flux:input wire:model="blockReason" label="Motivo" placeholder="Falta de pago" required />
            <div class="flex justify-end gap-2 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                <flux:modal.close>
                    <flux:button variant="subtle">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger">Bloquear</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
