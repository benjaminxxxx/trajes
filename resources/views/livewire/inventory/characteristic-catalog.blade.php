<div
    class="rounded-[2rem] border border-white/80 bg-white/70 p-5 shadow-xl shadow-slate-900/[0.04] backdrop-blur-xl sm:p-8 dark:border-slate-800 dark:bg-slate-900/75">

    <!-- Header -->
    <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div class="space-y-4">
            <flux:breadcrumbs>
                <flux:breadcrumbs.item :href="route('dashboard')" icon="home" />
                <flux:breadcrumbs.item>Inventario</flux:breadcrumbs.item>
                <flux:breadcrumbs.item :href="route('inventory.characteristics.index')">Telas, cortes y más</flux:breadcrumbs.item>
            </flux:breadcrumbs>

            <div>
                <flux:heading size="xl">Telas, cortes y más</flux:heading>
                <flux:text class="mt-1.5">
                    Lo que se pide al registrar un producto y las opciones que se sugieren. Son solo sugerencias:
                    cada producto guarda su propio valor, así que cambiar algo aquí no altera lo ya registrado.
                </flux:text>
            </div>
        </div>

        <flux:button wire:click="create" variant="primary" icon="plus">Nueva característica</flux:button>
    </div>

    <!-- Lista -->
    <div class="mt-7 grid gap-4 lg:grid-cols-2">
        @forelse ($characteristics as $characteristic)
            <div wire:key="characteristic-{{ $characteristic->id }}" @class([
                'rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900',
                'opacity-60' => ! $characteristic->is_active,
            ])>
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="flex items-center gap-2 font-semibold text-zinc-900 dark:text-white">
                            {{ $characteristic->name }}
                            @unless ($characteristic->is_active)
                                <flux:badge size="sm" color="zinc">No se pide</flux:badge>
                            @endunless
                        </p>
                        <p class="mt-0.5 text-xs text-zinc-500">
                            {{ $characteristic->categories->isEmpty() ? 'Todas las categorías' : $characteristic->categories->pluck('name')->implode(', ') }}
                            · {{ trans_choice(':count producto|:count productos', $characteristic->product_characteristics_count) }}
                        </p>
                    </div>
                    <div class="flex shrink-0 gap-1">
                        <flux:button size="sm" variant="ghost" icon="pencil" wire:click="edit({{ $characteristic->id }})" aria-label="Editar" />
                        <flux:button size="sm" variant="ghost" :icon="$characteristic->is_active ? 'eye-slash' : 'eye'"
                            wire:click="toggleActive({{ $characteristic->id }})"
                            :aria-label="$characteristic->is_active ? 'Dejar de pedir' : 'Volver a pedir'" />
                        <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $characteristic->id }})"
                            wire:confirm="¿Eliminar «{{ $characteristic->name }}» y sus sugerencias? Los productos conservan su valor."
                            aria-label="Eliminar" />
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap gap-1.5">
                    @forelse ($characteristic->values as $value)
                        <span wire:key="characteristic-value-{{ $value->id }}"
                            class="inline-flex items-center gap-1 rounded-full bg-violet-50 py-0.5 pl-2.5 pr-1 text-xs text-violet-700 dark:bg-violet-950/40 dark:text-violet-300">
                            {{ $value->value }}
                            <button type="button" wire:click="removeValue({{ $characteristic->id }}, {{ $value->id }})"
                                class="grid size-4 place-items-center rounded-full hover:bg-violet-200 dark:hover:bg-violet-900"
                                aria-label="Quitar {{ $value->value }}">
                                <flux:icon icon="x-mark" class="size-3" />
                            </button>
                        </span>
                    @empty
                        <span class="text-xs text-zinc-400">Sin sugerencias aún: se agregan solas al registrar productos.</span>
                    @endforelse
                </div>

                <form wire:submit="addValue({{ $characteristic->id }})" class="mt-3 flex gap-2">
                    <flux:input size="sm" wire:model="newValues.{{ $characteristic->id }}" placeholder="Agregar sugerencia..." class="flex-1" />
                    <flux:button size="sm" type="submit" icon="plus" aria-label="Agregar sugerencia" />
                </form>
            </div>
        @empty
            <div class="rounded-2xl border border-dashed border-zinc-300 p-10 text-center text-sm text-zinc-500 lg:col-span-2 dark:border-zinc-700">
                Aún no hay características. Crea «Tela», «Corte» o «Cuero», o agrégalas al registrar un producto.
            </div>
        @endforelse
    </div>

    <!-- Formulario -->
    <flux:modal wire:model="isFormOpen" class="w-full max-w-md">
        <form wire:submit="save" class="flex flex-col gap-5">
            <flux:heading size="lg">{{ $editingId ? 'Editar característica' : 'Nueva característica' }}</flux:heading>

            <flux:input wire:model="name" label="Nombre" placeholder="Tela, Corte, Cuero..." required />

            <flux:checkbox.group wire:model="category_ids" label="Se pide en"
                description="Si no marcas ninguna, se pide en todas las categorías.">
                <div class="grid max-h-56 grid-cols-2 gap-2 overflow-y-auto">
                    @foreach ($categories as $category)
                        <flux:checkbox value="{{ $category->id }}" :label="$category->name" />
                    @endforeach
                </div>
            </flux:checkbox.group>

            <flux:switch wire:model="is_active" label="Pedirla al registrar productos" align="left" />

            <div class="flex justify-end gap-2 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                <flux:modal.close>
                    <flux:button variant="subtle">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Guardar</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
