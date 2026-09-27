<div
    class="rounded-[2rem] border border-white/80 bg-white/70 p-5 shadow-xl shadow-slate-900/[0.04] backdrop-blur-xl sm:p-8 dark:border-slate-800 dark:bg-slate-900/75">

    <!-- Header -->
    <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div class="space-y-4">
            <!-- Breadcrumbs de navegación -->
            <flux:breadcrumbs>
                <flux:breadcrumbs.item :href="route('dashboard')" icon="home" />
                <flux:breadcrumbs.item :href="route('core.clients.index')">Clientes</flux:breadcrumbs.item>
            </flux:breadcrumbs>

            <!-- Header / Título principal -->
            <div>
                <flux:heading size="xl">
                    Tus clientes
                </flux:heading>

                <flux:text class="mt-1.5">
                    Consulta, edita y revisa la actividad de cada relación.
                </flux:text>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <flux:button wire:click="openCreateModal" variant="primary" icon="plus">
                Agregar cliente
            </flux:button>

            <flux:button wire:click="$toggle('showArchived')" variant="outline"
                :icon="$showArchived ? 'users' : 'archive-box'">
                {{ $showArchived ? 'Volver a clientes' : 'Dados de baja' }}
            </flux:button>
        </div>
    </div>

    <!-- Filtros y Búsqueda -->
    <div class="mt-7 flex flex-col gap-3 lg:flex-row">
        <!-- Input de búsqueda -->
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
            placeholder="Buscar por DNI, RUC, nombre o razón social..." />

        <!-- Filtro de tipo de cliente segmentado con Flux -->
        <flux:radio.group wire:model.live="filter" variant="segmented">
            <flux:radio value="all">Todos</flux:radio>
            <flux:radio value="individual">Personas</flux:radio>
            <flux:radio value="company">Compañías</flux:radio>
        </flux:radio.group>
    </div>

    <!-- Grid de Clientes -->
    <div class="mt-5 grid gap-3 sm:grid-cols-2">

        @forelse($clients as $client)
            @php
                // Generación de iniciales
                $words = explode(' ', $client->display_name);
                $initials =
                    count($words) >= 2
                        ? mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1)
                        : mb_substr($client->display_name, 0, 2);
                $initials = strtoupper($initials);

                // Estilos para las iniciales según tipo
                $tone =
                    $client->type === 'individual'
                        ? 'bg-violet-100 text-violet-700 dark:bg-violet-950/60 dark:text-violet-300'
                        : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300';
            @endphp

            <flux:card variant="soft" wire:key="client-{{ $client->id }}"
                wire:click="{{ $showArchived ? '' : 'openEditModal(' . $client->id . ')' }}"
                class="{{ !$showArchived ? 'cursor-pointer hover:-translate-y-0.5 transition-transform' : '' }}">
                <div class="flex items-center gap-3">
                    <!-- Avatar con Iniciales -->
                    <flux:avatar :name="$client->display_name" :color="$client->avatar_color" variant="circle"
                        class="size-10 shrink-0 text-xs font-bold" />

                    <!-- Información Principal -->
                    <div class="min-w-0 flex-1">
                        <flux:heading size="base" class="truncate font-semibold">
                            {{ $client->display_name }}
                        </flux:heading>

                        <flux:text class="mt-0.5 text-xs">
                            <span
                                class="font-medium text-slate-700 dark:text-slate-300">{{ $client->document_type }}:</span>
                            {{ $client->document_number }}
                        </flux:text>
                    </div>

                    @unless ($showArchived)
                        <flux:icon icon="chevron-right" class="size-4 shrink-0 text-slate-400" />
                    @endunless
                </div>

                <!-- Métricas -->
                <div class="mt-4 flex items-center gap-2 text-[11px] text-slate-500 dark:text-slate-400">
                    <span>{{ $client->sales_count ?? 0 }} ventas</span>
                    <span>•</span>
                    <span>{{ $client->rentals_count ?? 0 }} alquileres</span>
                    <span>•</span>
                    <span>{{ $client->invoices_count ?? 0 }} facturas</span>
                </div>

                <!-- Acciones si está archivado -->
                @if ($showArchived)
                    <div class="mt-4 flex gap-2 pt-3 border-t border-slate-200/60 dark:border-slate-800">
                        <flux:button wire:click.stop="restoreClient({{ $client->id }})" variant="primary"
                            size="sm" class="flex-1">
                            Restaurar
                        </flux:button>

                        <flux:button wire:click.stop="forceDeleteClient({{ $client->id }})"
                            wire:confirm="¿Estás seguro de eliminar este cliente permanentemente?" variant="danger"
                            size="sm" class="flex-1">
                            Eliminar
                        </flux:button>
                    </div>
                @endif
            </flux:card>
        @empty
            <div
                class="col-span-full rounded-2xl border border-dashed border-slate-200 p-12 text-center dark:border-slate-800">
                <flux:text class="text-slate-500 dark:text-slate-400">
                    {{ $showArchived ? 'No hay clientes dados de baja.' : 'No se encontraron clientes con los filtros seleccionados.' }}
                </flux:text>
            </div>
        @endforelse
        <!-- Paginación -->

    </div>
    <div class="flex justify-end mt-6">
        {{ $clients->links() }}
    </div>


    <livewire:core.client-detail-modal />
    <livewire:core.client-form-modal />
</div>
