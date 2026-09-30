<div
    class="rounded-[2rem] border border-white/80 bg-white/70 p-5 shadow-xl shadow-slate-900/[0.04] backdrop-blur-xl sm:p-8 dark:border-slate-800 dark:bg-slate-900/75">

    <!-- Header -->
    <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div class="space-y-4">
            <flux:breadcrumbs>
                <flux:breadcrumbs.item :href="route('dashboard')" icon="home" />
                <flux:breadcrumbs.item>Inventario</flux:breadcrumbs.item>
                <flux:breadcrumbs.item :href="route('inventory.categories.index')">Categorías</flux:breadcrumbs.item>
            </flux:breadcrumbs>

            <div>
                <flux:heading size="xl">
                    Categorías
                </flux:heading>

                <flux:text class="mt-1.5">
                    Organiza el catálogo y define las tallas y colores de cada categoría.
                </flux:text>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <flux:button wire:click="openCreateModal" variant="primary" icon="plus">
                Agregar categoría
            </flux:button>

            <flux:button icon="chart-bar" wire:click="$dispatch('open-category-report')">
                Reporte anual
            </flux:button>

            <flux:button wire:click="$toggle('showArchived')" variant="outline"
                :icon="$showArchived ? 'tag' : 'archive-box'">
                {{ $showArchived ? 'Volver a categorías' : 'Dadas de baja' }}
            </flux:button>
        </div>
    </div>

    <!-- Búsqueda -->
    <div class="mt-7">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
            placeholder="Buscar categoría..." />
    </div>

    <!-- Grid de Categorías -->
    <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        @forelse($categories as $category)
            <flux:card variant="soft" wire:key="category-{{ $category->id }}" class="flex flex-col">
                <div class="flex items-start gap-3">
                    <span
                        class="grid size-10 shrink-0 place-items-center rounded-xl bg-violet-100 dark:bg-violet-950/60">
                        <flux:icon icon="tag" class="size-5 text-violet-600 dark:text-violet-400" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <flux:heading size="base" class="truncate font-semibold">
                                {{ $category->name }}
                            </flux:heading>

                            @unless ($category->is_active)
                                <flux:badge size="sm" color="red">Inactiva</flux:badge>
                            @endunless
                        </div>

                        <flux:text class="mt-0.5 truncate text-xs">
                            {{ $category->parent ? 'Dentro de ' . $category->parent->name : ($category->description ?: 'Sin descripción') }}
                        </flux:text>
                    </div>
                </div>

                <!-- Métricas -->
                <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-xl bg-zinc-50 px-2 py-2 dark:bg-zinc-800/60">
                        <p class="text-lg font-semibold tabular-nums text-zinc-900 dark:text-white">
                            {{ $category->products_count }}</p>
                        <p class="text-[11px] text-zinc-500">Productos</p>
                    </div>
                    <div class="rounded-xl bg-zinc-50 px-2 py-2 dark:bg-zinc-800/60">
                        <p class="text-lg font-semibold tabular-nums text-zinc-900 dark:text-white">
                            {{ $units['rentals'][$category->id] ?? 0 }}</p>
                        <p class="text-[11px] text-zinc-500">Alquilados</p>
                    </div>
                    <div class="rounded-xl bg-zinc-50 px-2 py-2 dark:bg-zinc-800/60">
                        <p class="text-lg font-semibold tabular-nums text-zinc-900 dark:text-white">
                            {{ $units['sales'][$category->id] ?? 0 }}</p>
                        <p class="text-[11px] text-zinc-500">Vendidos</p>
                    </div>
                </div>

                <!-- Tallas y colores -->
                <div class="mt-4 space-y-2">
                    <div class="flex flex-wrap items-center gap-1">
                        <span class="mr-1 text-[11px] font-semibold uppercase tracking-wide text-zinc-400">Tallas</span>
                        @forelse ($category->sizes as $size)
                            <span
                                class="rounded-md border border-zinc-200 px-1.5 py-0.5 text-[11px] text-zinc-600 dark:border-zinc-700 dark:text-zinc-300">{{ $size->name }}</span>
                        @empty
                            <span class="text-[11px] text-zinc-400">Talla única</span>
                        @endforelse
                    </div>

                    <div class="flex flex-wrap items-center gap-1">
                        <span class="mr-1 text-[11px] font-semibold uppercase tracking-wide text-zinc-400">Colores</span>
                        @forelse ($category->colors as $color)
                            <flux:tooltip :content="$color->name">
                                <span class="size-4 rounded-full border border-zinc-300 dark:border-zinc-600"
                                    style="background-color: {{ $color->hex_code ?? '#e4e4e7' }}"></span>
                            </flux:tooltip>
                        @empty
                            <span class="text-[11px] text-zinc-400">Sin colores</span>
                        @endforelse
                    </div>
                </div>

                <!-- Acciones -->
                <div class="mt-auto pt-4">
                    <div class="flex gap-2 border-t border-slate-200/60 pt-3 dark:border-slate-800">
                        @if ($showArchived)
                            <flux:button wire:click="restoreCategory({{ $category->id }})" variant="primary"
                                size="sm" class="flex-1">
                                Restaurar
                            </flux:button>
                        @else
                            <flux:button wire:click="openEditModal({{ $category->id }})" size="sm"
                                icon="pencil-square" class="flex-1">
                                Editar
                            </flux:button>
                            <flux:button :href="route('inventory.products.index', ['category' => $category->id])"
                                wire:navigate size="sm" icon="squares-2x2" class="flex-1">
                                Productos
                            </flux:button>
                            <flux:button
                                wire:click="$dispatch('open-category-report', { categoryId: {{ $category->id }} })"
                                size="sm" icon="chart-bar" variant="subtle" />
                        @endif
                    </div>
                </div>
            </flux:card>
        @empty
            <div
                class="col-span-full rounded-2xl border border-dashed border-slate-200 p-12 text-center dark:border-slate-800">
                <flux:text class="text-slate-500 dark:text-slate-400">
                    {{ $showArchived ? 'No hay categorías dadas de baja.' : 'No se encontraron categorías.' }}
                </flux:text>
            </div>
        @endforelse
    </div>

    <div class="mt-6 flex justify-end">
        {{ $categories->links() }}
    </div>

    <livewire:inventory.category-form-modal />
    <livewire:inventory.category-year-report />
</div>
