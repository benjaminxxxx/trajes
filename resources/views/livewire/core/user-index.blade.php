<div
    class="rounded-[2rem] border border-white/80 bg-white/70 p-5 shadow-xl shadow-slate-900/[0.04] backdrop-blur-xl sm:p-8 dark:border-slate-800 dark:bg-slate-900/75">

    <!-- Header -->
    <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div class="space-y-4">
            <flux:breadcrumbs>
                <flux:breadcrumbs.item :href="route('dashboard')" icon="home" />
                <flux:breadcrumbs.item>Seguridad</flux:breadcrumbs.item>
                <flux:breadcrumbs.item :href="route('core.users.index')">Usuarios</flux:breadcrumbs.item>
            </flux:breadcrumbs>

            <div>
                <flux:heading size="xl">
                    Usuarios del sistema
                </flux:heading>

                <flux:text class="mt-1.5">
                    Personas con acceso a este negocio. Retirar a alguien no borra su historial ni su cuenta en otros negocios.
                </flux:text>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <flux:button wire:click="openCreateModal" variant="primary" icon="plus">
                Agregar persona
            </flux:button>

            <flux:button wire:click="$toggle('showArchived')" variant="outline"
                :icon="$showArchived ? 'users' : 'archive-box'">
                {{ $showArchived ? 'Volver a usuarios' : 'Retirados' }}
            </flux:button>
        </div>
    </div>

    <!-- Filtros y Búsqueda -->
    <div class="mt-7 flex flex-col gap-3 lg:flex-row">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
            placeholder="Buscar por nombre o correo..." />

        <flux:radio.group wire:model.live="role" variant="segmented">
            <flux:radio value="all">Todos</flux:radio>
            <flux:radio value="admin">Administradores</flux:radio>
            <flux:radio value="vendedor">Vendedores</flux:radio>
        </flux:radio.group>
    </div>

    <!-- Grid de Usuarios -->
    <div class="mt-5 grid gap-3 sm:grid-cols-2">
        @forelse($users as $user)
            @php
                $roleName = $user->roles->first()?->name;
            @endphp

            <flux:card variant="soft" wire:key="user-{{ $user->id }}"
                wire:click="{{ $showArchived ? '' : 'openEditModal(' . $user->id . ')' }}"
                class="{{ !$showArchived ? 'cursor-pointer hover:-translate-y-0.5 transition-transform' : '' }}">
                <div class="flex items-center gap-3">
                    <flux:avatar :name="$user->name" color="auto" variant="circle"
                        class="size-10 shrink-0 text-xs font-bold" />

                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <flux:heading size="base" class="truncate font-semibold">
                                {{ $user->name }}
                            </flux:heading>

                            @if ($user->id === $ownerId)
                                <flux:badge size="sm" color="amber" icon="star">Dueño</flux:badge>
                            @endif
                            @if ($user->id === auth()->id())
                                <flux:badge size="sm" color="zinc">Tú</flux:badge>
                            @endif
                        </div>

                        <flux:text class="mt-0.5 truncate text-xs">
                            {{ $user->email }}
                        </flux:text>
                    </div>

                    <div class="flex shrink-0 flex-col items-end gap-1">
                        @if ($roleName === 'admin')
                            <flux:badge size="sm" color="violet" icon="shield-check">Admin</flux:badge>
                        @elseif ($roleName === 'vendedor')
                            <flux:badge size="sm" color="emerald" icon="shopping-bag">Vendedor</flux:badge>
                        @endif

                    </div>
                </div>

                <!-- Métricas -->
                @if ($roleName === 'vendedor')
                    <div class="mt-4 flex items-center gap-2 text-[11px] text-slate-500 dark:text-slate-400">
                        <span>{{ $user->rentals_count }} alquileres</span>
                        <span>•</span>
                        <span>{{ $user->sales_count }} ventas</span>
                    </div>
                @endif

                @if ($showArchived)
                    <div class="mt-4 flex gap-2 border-t border-slate-200/60 pt-3 dark:border-slate-800">
                        <flux:button wire:click.stop="restoreUser({{ $user->id }})" variant="primary" size="sm"
                            class="flex-1">
                            Devolver acceso
                        </flux:button>
                    </div>
                @endif
            </flux:card>
        @empty
            <div
                class="col-span-full rounded-2xl border border-dashed border-slate-200 p-12 text-center dark:border-slate-800">
                <flux:text class="text-slate-500 dark:text-slate-400">
                    {{ $showArchived ? 'No hay personas retiradas.' : 'No se encontraron usuarios con los filtros seleccionados.' }}
                </flux:text>
            </div>
        @endforelse
    </div>

    <div class="mt-6 flex justify-end">
        {{ $users->links() }}
    </div>

    <livewire:core.user-form-modal />
</div>
