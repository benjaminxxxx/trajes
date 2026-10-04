<div class="space-y-6">
    <!-- Header responsive -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" class="text-zinc-900 dark:text-white">Productos</flux:heading>
            <flux:subheading class="text-zinc-500 dark:text-zinc-400">
                Gestión del catálogo general de prendas y artículos
            </flux:subheading>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="create" class="w-full sm:w-auto">
            Nuevo producto
        </flux:button>
    </div>

    <!-- Filtros adaptables (Mobile stack / Desktop inline) -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:flex lg:items-center">
        <flux:input
            wire:model.live.debounce.300ms="search"
            placeholder="Buscar por nombre o código..."
            icon="magnifying-glass"
            class="w-full lg:max-w-xs"
        />

        <flux:select wire:model.live="categoryFilter" placeholder="Todas las categorías" class="w-full lg:max-w-xs">
            <flux:select.option value="">Todas las categorías</flux:select.option>
            @foreach ($categoryOptions as $category)
                <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <div class="flex items-center justify-between sm:justify-start">
            <flux:switch wire:model.live="showTrashed" label="Ver eliminados" />
        </div>
    </div>

    <!-- VISTA MÓVIL Y TABLET (Cards) -->
    <div class="grid grid-cols-1 gap-4 sm:hidden">
        @forelse ($products as $product)
            <div wire:key="mobile-product-{{ $product->id }}" 
                 class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm transition dark:border-zinc-800 dark:bg-zinc-900">
                
                <div class="flex items-start gap-3">
                    @if ($product->media->first())
                        <img src="{{ Storage::url($product->media->first()->file_path) }}" 
                             class="h-16 w-16 flex-shrink-0 rounded-lg object-cover ring-1 ring-zinc-200 dark:ring-zinc-700" />
                    @else
                        <div class="flex h-16 w-16 flex-shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-400 dark:bg-zinc-800 dark:text-zinc-600">
                            <flux:icon icon="photo" class="h-8 w-8" />
                        </div>
                    @endif

                    <div class="flex-1 space-y-1">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">#{{ $product->code }}</span>
                            <flux:badge size="sm" :color="$product->is_active ? 'green' : 'zinc'">
                                {{ $product->is_active ? 'Activo' : 'Inactivo' }}
                            </flux:badge>
                        </div>
                        
                        <h4 class="font-semibold text-zinc-900 dark:text-white">{{ $product->name }}</h4>
                        
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">
                            {{ $product->category?->name ?? 'Sin categoría' }} • <span class="capitalize">{{ $product->gender }}</span>
                        </div>

                        <div class="flex items-center justify-between pt-2">
                            <span class="text-base font-bold text-zinc-900 dark:text-white">
                                S/ {{ number_format($product->price, 2) }}
                            </span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                Stock: {{ $product->items_count ?? 0 }} unids.
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Acciones móviles -->
                <div class="mt-4 flex items-center justify-end gap-2 border-t border-zinc-100 pt-3 dark:border-zinc-800">
                    @if ($product->trashed())
                        <flux:button size="sm" variant="ghost" icon="arrow-uturn-left"
                            wire:click="restore({{ $product->id }})">
                            Restaurar
                        </flux:button>
                    @else
                        <flux:button size="sm" variant="ghost" icon="pencil"
                            wire:click="edit({{ $product->id }})">
                            Editar
                        </flux:button>
                        <flux:button size="sm" variant="ghost" icon="photo"
                            wire:click="manageMedia({{ $product->id }})">
                            Fotos
                        </flux:button>
                        @can('inventario.seguimiento')
                            <flux:button size="sm" variant="ghost" icon="chart-bar" wire:navigate
                                :href="route('inventory.physical-items.index', ['producto' => $product->id])">
                                Seguimiento
                            </flux:button>
                        @endcan
                        <flux:button size="sm" variant="ghost" icon="trash" color="red"
                            wire:click="delete({{ $product->id }})"
                            wire:confirm="¿Desea enviar a papelera este producto?">
                        </flux:button>
                    @endif
                </div>
            </div>
        @empty
            <div class="rounded-lg border border-dashed border-zinc-300 p-8 text-center dark:border-zinc-700">
                <p class="text-zinc-500 dark:text-zinc-400">No se encontraron productos registrados.</p>
            </div>
        @endforelse
    </div>

    <!-- VISTA DESKTOP / TABLET ANCHA (Tabla) -->
    <div class="hidden sm:block">
        <flux:table class="dark:border-zinc-800">
            <flux:table.columns>
                <flux:table.column>Foto</flux:table.column>
                <flux:table.column>Código</flux:table.column>
                <flux:table.column>Nombre</flux:table.column>
                <flux:table.column>Categoría/Marca</flux:table.column>
                <flux:table.column>Género</flux:table.column>
                <flux:table.column>Precio Base</flux:table.column>
                <flux:table.column>Estado</flux:table.column>
                <flux:table.column class="text-right">Acciones</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($products as $product)
                    <flux:table.row wire:key="product-row-{{ $product->id }}" class="dark:hover:bg-zinc-800/50">
                        <flux:table.cell>
                            @if ($product->media->first())
                                <img src="{{ Storage::url($product->media->first()->file_path) }}" 
                                     class="h-10 w-10 rounded-md object-cover ring-1 ring-zinc-200 dark:ring-zinc-700" />
                            @else
                                <div class="flex h-10 w-10 items-center justify-center rounded-md bg-zinc-100 text-zinc-400 dark:bg-zinc-800 dark:text-zinc-500">
                                    <flux:icon icon="photo" class="h-5 w-5" />
                                </div>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell class="font-mono text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                            {{ $product->code }}
                        </flux:table.cell>

                        <flux:table.cell class="font-medium text-zinc-900 dark:text-white">
                            {{ $product->name }}
                        </flux:table.cell>

                        <flux:table.cell class="text-zinc-500 dark:text-zinc-400">
                            <div>{{ $product->category?->name ?? '—' }}</div>
                            <div class="text-xs text-zinc-400 dark:text-zinc-500">{{ $product->brand?->name ?? 'Sin marca' }}</div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <span class="capitalize text-zinc-600 dark:text-zinc-400">{{ $product->gender }}</span>
                        </flux:table.cell>

                        <flux:table.cell class="font-semibold text-zinc-900 dark:text-white">
                            S/ {{ number_format($product->price, 2) }}
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge size="sm" :color="$product->is_active ? 'green' : 'zinc'">
                                {{ $product->is_active ? 'Activo' : 'Inactivo' }}
                            </flux:badge>
                        </flux:table.cell>

                        <flux:table.cell class="text-right">
                            <div class="flex items-center justify-end gap-1">
                                @if ($product->trashed())
                                    <flux:button size="sm" variant="ghost" icon="arrow-uturn-left"
                                        wire:click="restore({{ $product->id }})">
                                        Restaurar
                                    </flux:button>
                                @else
                                    <flux:button size="sm" variant="ghost" icon="pencil"
                                        wire:click="edit({{ $product->id }})" />
                                    
                                    <flux:button size="sm" variant="ghost" icon="photo"
                                        @click="$wire.dispatch('manageMedia',{productId:{{ $product->id }}})" />

                                    @can('inventario.seguimiento')
                                        <flux:tooltip content="Seguimiento de prendas">
                                            <flux:button size="sm" variant="ghost" icon="chart-bar" wire:navigate
                                                :href="route('inventory.physical-items.index', ['producto' => $product->id])"
                                                aria-label="Seguimiento de prendas" />
                                        </flux:tooltip>
                                    @endcan

                                    <flux:button size="sm" variant="ghost" icon="trash" color="red"
                                        wire:click="delete({{ $product->id }})"
                                        wire:confirm="¿Mover a la papelera?" />
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="text-center py-8">
                            <span class="text-zinc-400 dark:text-zinc-500">No se encontraron registros de productos.</span>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <!-- Paginación -->
    <div class="mt-4">
        {{ $products->links() }}
    </div>

    <livewire:inventory.product-media-manager />
    <livewire:inventory.product-form-modal />
</div>