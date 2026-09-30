<div
    class="rounded-[2rem] border border-white/80 bg-white/70 p-5 shadow-xl shadow-slate-900/[0.04] backdrop-blur-xl sm:p-8 dark:border-slate-800 dark:bg-slate-900/75">

    <!-- Header -->
    <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div class="space-y-4">
            <flux:breadcrumbs>
                <flux:breadcrumbs.item :href="route('dashboard')" icon="home" />
                <flux:breadcrumbs.item>Inventario</flux:breadcrumbs.item>
                <flux:breadcrumbs.item :href="route('inventory.attributes.index')">Tallas, colores y marcas</flux:breadcrumbs.item>
            </flux:breadcrumbs>

            <div>
                <flux:heading size="xl">Tallas, colores y marcas</flux:heading>
                <flux:text class="mt-1.5">
                    Lo que ya usa algún producto no se puede renombrar: desactívalo y crea uno nuevo, así el historial no cambia.
                </flux:text>
            </div>
        </div>

        <flux:button wire:click="create" variant="primary" icon="plus">
            {{ ['tallas' => 'Nueva talla', 'colores' => 'Nuevo color', 'marcas' => 'Nueva marca'][$tab] }}
        </flux:button>
    </div>

    <!-- Pestañas y búsqueda -->
    <div class="mt-7 flex flex-col gap-3 lg:flex-row lg:items-center">
        <flux:radio.group wire:model.live="tab" variant="segmented">
            <flux:radio value="tallas">Tallas ({{ $counts['tallas'] }})</flux:radio>
            <flux:radio value="colores">Colores ({{ $counts['colores'] }})</flux:radio>
            <flux:radio value="marcas">Marcas ({{ $counts['marcas'] }})</flux:radio>
        </flux:radio.group>

        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Buscar por nombre..." />
    </div>

    <!-- Tabla -->
    <div class="mt-5 overflow-x-auto rounded-2xl border border-zinc-200 dark:border-zinc-800">
        <table class="w-full min-w-[40rem] text-sm">
            <thead class="bg-zinc-50 text-xs text-zinc-500 dark:bg-zinc-800/60">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold">Nombre</th>
                    @if ($tab === 'tallas')
                        <th class="px-4 py-3 text-left font-semibold">Grupo</th>
                        <th class="px-4 py-3 text-right font-semibold">Orden</th>
                    @endif
                    <th class="px-4 py-3 text-right font-semibold">En uso</th>
                    <th class="px-4 py-3 text-left font-semibold">Estado</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($records as $record)
                    @php($inUse = $tab === 'marcas' ? $record->products_count > 0 : $record->variants_count > 0)
                    <tr wire:key="{{ $tab }}-{{ $record->id }}" @class(['opacity-55' => ! $record->is_active])>
                        <td class="px-4 py-3">
                            <span class="flex items-center gap-2 font-medium text-zinc-900 dark:text-white">
                                @if ($tab === 'colores')
                                    <span class="size-4 rounded-full border border-zinc-300 dark:border-zinc-600"
                                        style="background-color: {{ $record->hex_code ?? '#e4e4e7' }}"></span>
                                @endif
                                {{ $record->name }}
                                @if ($inUse)
                                    <flux:tooltip content="En uso: el nombre no se puede cambiar">
                                        <flux:icon icon="lock-closed" class="size-3.5 text-zinc-400" />
                                    </flux:tooltip>
                                @endif
                            </span>
                        </td>
                        @if ($tab === 'tallas')
                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ $sizeTypes[$record->type] ?? $record->type }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-zinc-500">{{ $record->sort_order }}</td>
                        @endif
                        <td class="px-4 py-3 text-right text-xs text-zinc-500">
                            @if ($tab === 'marcas')
                                {{ $record->products_count }} productos
                            @else
                                {{ $record->variants_count }} variantes · {{ $record->categories_count }} categorías
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <flux:badge size="sm" :color="$record->is_active ? 'emerald' : 'zinc'">
                                {{ $record->is_active ? 'Activa' : 'Inactiva' }}
                            </flux:badge>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-1">
                                <flux:button size="sm" variant="ghost" icon="pencil" wire:click="edit({{ $record->id }})" aria-label="Editar" />
                                <flux:button size="sm" variant="ghost" :icon="$record->is_active ? 'eye-slash' : 'eye'"
                                    wire:click="toggleActive({{ $record->id }})"
                                    :aria-label="$record->is_active ? 'Desactivar' : 'Activar'" />
                                @unless ($inUse)
                                    <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $record->id }})"
                                        wire:confirm="¿Eliminar «{{ $record->name }}»?" aria-label="Eliminar" />
                                @endunless
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-zinc-500">No hay registros.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Formulario -->
    <flux:modal wire:model="isFormOpen" class="w-full max-w-md">
        <form wire:submit="save" class="flex flex-col gap-5">
            <div>
                <flux:heading size="lg">
                    {{ $editingId ? 'Editar' : 'Nueva' }} {{ ['tallas' => 'talla', 'colores' => 'color', 'marcas' => 'marca'][$tab] }}
                </flux:heading>
            </div>

            @if ($nameLocked)
                <flux:callout icon="lock-closed" variant="warning">
                    <flux:callout.text>
                        Ya hay productos con este nombre, por eso no se puede cambiar. Si ya no la usas,
                        desactívala y crea una nueva.
                    </flux:callout.text>
                </flux:callout>
            @endif

            <flux:input wire:model="name" label="Nombre" :disabled="$nameLocked" required
                :placeholder="['tallas' => 'M, 40, 12...', 'colores' => 'Azul noche', 'marcas' => 'Barrington'][$tab]" />

            @if ($tab === 'tallas')
                <div class="grid gap-4 sm:grid-cols-[1fr_7rem]">
                    <flux:input wire:model="type" label="Grupo" list="size-types" required
                        description="Agrupa las tallas en categorías y compras." />
                    <flux:input wire:model="sort_order" type="number" min="0" label="Orden" />
                </div>
                <datalist id="size-types">
                    @foreach ($sizeTypes as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </datalist>
            @endif

            @if ($tab === 'colores')
                <div class="flex items-end gap-3">
                    <input type="color" wire:model.live="hex_code" class="h-10 w-14 cursor-pointer rounded-lg border border-zinc-200 dark:border-zinc-700" aria-label="Color" />
                    <flux:input wire:model.live="hex_code" label="Código de color" placeholder="#1B263B" class="flex-1" />
                </div>
            @endif

            <flux:switch wire:model="is_active" label="Activa (se ofrece en categorías, compras y productos)" align="left" />

            <div class="flex justify-end gap-2 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                <flux:modal.close>
                    <flux:button variant="subtle">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Guardar</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
