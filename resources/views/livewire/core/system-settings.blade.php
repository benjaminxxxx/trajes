<div
    class="rounded-[2rem] border border-white/80 bg-white/70 p-5 shadow-xl shadow-slate-900/[0.04] backdrop-blur-xl sm:p-8 dark:border-slate-800 dark:bg-slate-900/75">

    <div class="space-y-4">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('dashboard')" icon="home" />
            <flux:breadcrumbs.item>Administración</flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('core.settings.index')">Configuración</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <flux:heading size="xl">Configuración</flux:heading>
    </div>

    <form wire:submit="save" class="mt-7 max-w-2xl rounded-2xl border border-zinc-200 p-5 dark:border-zinc-800">
        <flux:heading size="lg">Desgaste de prendas de alquiler</flux:heading>
        <flux:text class="mt-1">
            Cada vez que una prenda se alquila pierde valor. Se aplica la regla más alta alcanzada:
            valor = costo de compra × (1 − %). Se usa en el valorizado de la empresa.
        </flux:text>

        <div class="mt-5 flex flex-col gap-3">
            @forelse ($sortedRules as $key => $rule)
                <div class="flex flex-wrap items-end gap-3" wire:key="rule-{{ $key }}">
                    <flux:input wire:model="rules.{{ $key }}.min_rentals" type="number" min="1" label="Desde (alquileres)" class="!w-40" />
                    <flux:input wire:model="rules.{{ $key }}.percent" type="number" step="0.01" min="0" max="100" label="Pierde (%)" class="!w-32" />
                    <flux:button type="button" variant="ghost" icon="trash" wire:click="removeRule('{{ $key }}')" aria-label="Quitar regla" />
                </div>
            @empty
                <flux:text>Sin reglas: las prendas conservan su costo de compra.</flux:text>
            @endforelse
        </div>

        <div class="mt-5 flex flex-wrap justify-between gap-2 border-t border-zinc-100 pt-4 dark:border-zinc-800">
            <flux:button type="button" icon="plus" wire:click="addRule">Agregar regla</flux:button>
            <flux:button type="submit" variant="primary">Guardar</flux:button>
        </div>
    </form>
</div>
