<flux:modal wire:model="isCategoryFormOpen" class="w-full max-w-2xl">
    <div class="flex flex-col gap-5">
        <!-- Header -->
        <div class="border-b border-zinc-100 pb-4 dark:border-zinc-800">
            <div class="mb-2 flex items-center gap-2 text-xs font-semibold text-violet-600 dark:text-violet-400">
                <span class="grid size-6 place-items-center rounded-lg bg-violet-100 dark:bg-violet-950/60">
                    <flux:icon :icon="$editingId ? 'pencil-square' : 'plus'"
                        class="size-3.5 text-violet-600 dark:text-violet-400" />
                </span>
                <span>{{ $editingId ? 'Editar registro' : 'Nuevo registro' }}</span>
            </div>
            <h2 class="text-2xl font-semibold text-zinc-900 dark:text-white">
                {{ $editingId ? 'Editar categoría' : 'Agregar categoría' }}
            </h2>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                Las tallas y colores elegidos son los que se ofrecen al registrar productos de esta categoría.
            </p>
        </div>

        <form wire:submit="save" class="flex flex-col gap-5">
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="name" label="Nombre" placeholder="Ternos de Caballero" required />

                <flux:select wire:model="parent_id" label="Categoría padre">
                    <flux:select.option value="">Ninguna</flux:select.option>
                    @foreach ($parentOptions as $option)
                        <flux:select.option value="{{ $option->id }}">{{ $option->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <div class="sm:col-span-2">
                    <flux:textarea wire:model="description" label="Descripción" rows="2"
                        placeholder="Qué tipo de prendas agrupa..." />
                </div>

                <flux:input wire:model="sort_order" type="number" min="0" label="Orden" />

                <div class="flex items-end pb-2">
                    <flux:switch wire:model="is_active" label="Categoría activa" align="left" />
                </div>
            </div>

            <!-- Tallas -->
            <div>
                <div class="mb-3 flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400">Tallas</p>
                    <span class="text-xs text-zinc-500">{{ count($selectedSizes) }} seleccionadas</span>
                </div>

                <div class="space-y-3">
                    @foreach ($sizesByType as $type => $sizes)
                        <div wire:key="size-type-{{ $type }}">
                            <p class="mb-1.5 text-[11px] capitalize text-zinc-500">{{ str_replace('_', ' ', $type) }}</p>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($sizes as $size)
                                    @php($isSelected = in_array($size->id, $selectedSizes))
                                    <button type="button" wire:key="size-{{ $size->id }}"
                                        wire:click="toggleSize({{ $size->id }})" @class([
                                            'min-w-9 rounded-lg border px-2 py-1 text-xs font-medium transition-colors',
                                            'border-violet-500 bg-violet-50 text-violet-700 dark:border-violet-500/60 dark:bg-violet-950/40 dark:text-violet-300' => $isSelected,
                                            'border-zinc-200 text-zinc-600 hover:border-zinc-300 dark:border-zinc-700 dark:text-zinc-300' => !$isSelected,
                                        ])>
                                        {{ $size->name }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
                <flux:error name="selectedSizes" class="mt-2" />
            </div>

            <!-- Colores -->
            <div>
                <div class="mb-3 flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400">Colores</p>
                    <span class="text-xs text-zinc-500">{{ count($selectedColors) }} seleccionados</span>
                </div>

                <div class="flex flex-wrap gap-1.5">
                    @foreach ($colors as $color)
                        @php($isSelected = in_array($color->id, $selectedColors))
                        <button type="button" wire:key="color-{{ $color->id }}"
                            wire:click="toggleColor({{ $color->id }})" @class([
                                'flex items-center gap-1.5 rounded-lg border px-2 py-1 text-xs font-medium transition-colors',
                                'border-violet-500 bg-violet-50 text-violet-700 dark:border-violet-500/60 dark:bg-violet-950/40 dark:text-violet-300' => $isSelected,
                                'border-zinc-200 text-zinc-600 hover:border-zinc-300 dark:border-zinc-700 dark:text-zinc-300' => !$isSelected,
                            ])>
                            <span class="size-3 rounded-full border border-zinc-300 dark:border-zinc-600"
                                style="background-color: {{ $color->hex_code ?? '#e4e4e7' }}"></span>
                            {{ $color->name }}
                        </button>
                    @endforeach
                </div>
                <flux:error name="selectedColors" class="mt-2" />
            </div>

            <!-- Footer y Acciones -->
            <div
                class="flex flex-col-reverse gap-3 border-t border-zinc-100 pt-5 sm:flex-row sm:items-center sm:justify-between dark:border-zinc-800">
                <div>
                    @if ($editingId)
                        <flux:button wire:click="deleteCategory"
                            wire:confirm="{{ $productsCount > 0 ? 'Esta categoría tiene ' . $productsCount . ' productos, será dada de baja. ¿Continuar?' : '¿Eliminar esta categoría permanentemente?' }}"
                            variant="danger" icon="trash">
                            Eliminar
                        </flux:button>
                    @endif
                </div>

                <div class="flex flex-col-reverse gap-3 sm:flex-row">
                    <flux:modal.close>
                        <flux:button variant="subtle">Cancelar</flux:button>
                    </flux:modal.close>

                    <flux:button type="submit" variant="primary">
                        <span wire:loading.remove wire:target="save">
                            {{ $editingId ? 'Guardar cambios' : 'Guardar categoría' }}
                        </span>
                        <span wire:loading wire:target="save">Guardando...</span>
                    </flux:button>
                </div>
            </div>
        </form>
    </div>
</flux:modal>
