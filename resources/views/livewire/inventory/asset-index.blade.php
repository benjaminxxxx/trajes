<div
    class="rounded-[2rem] border border-white/80 bg-white/70 p-5 shadow-xl shadow-slate-900/[0.04] backdrop-blur-xl sm:p-8 dark:border-slate-800 dark:bg-slate-900/75">

    <!-- Header -->
    <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div class="space-y-4">
            <flux:breadcrumbs>
                <flux:breadcrumbs.item :href="route('dashboard')" icon="home" />
                <flux:breadcrumbs.item>Inventario</flux:breadcrumbs.item>
                <flux:breadcrumbs.item :href="route('inventory.assets.index')">Bienes del negocio</flux:breadcrumbs.item>
            </flux:breadcrumbs>

            <div>
                <flux:heading size="xl">Bienes del negocio</flux:heading>
                <flux:text class="mt-1.5">
                    Mobiliario, maniquíes, ganchos y consumibles como portaternos. No se venden: suman al valor de la empresa.
                </flux:text>
            </div>
        </div>

        <div class="flex gap-2">
            @can('inventario.valorizado')
                <flux:button :href="route('inventory.valuation.index')" wire:navigate icon="scale">Valorizado</flux:button>
            @endcan
            <flux:button wire:click="create" variant="primary" icon="plus">Nuevo bien</flux:button>
        </div>
    </div>

    <!-- Totales -->
    <div class="mt-7 grid gap-3 sm:grid-cols-3">
        <div class="rounded-2xl bg-zinc-50 p-4 dark:bg-zinc-800/60">
            <p class="text-xs text-zinc-500">Bienes listados</p>
            <p class="mt-1 text-xl font-semibold tabular-nums">{{ $assets->count() }} <span class="text-sm font-normal text-zinc-500">· {{ $assets->sum('quantity') }} unidades</span></p>
        </div>
        <div class="rounded-2xl bg-zinc-50 p-4 dark:bg-zinc-800/60">
            <p class="text-xs text-zinc-500">Costo de compra</p>
            <p class="mt-1 text-xl font-semibold tabular-nums">S/ {{ number_format($totals['cost'], 2) }}</p>
        </div>
        <div class="rounded-2xl bg-violet-50 p-4 dark:bg-violet-950/30">
            <p class="text-xs text-violet-700 dark:text-violet-300">Valor actual</p>
            <p class="mt-1 text-xl font-semibold tabular-nums">S/ {{ number_format($totals['value'], 2) }}</p>
        </div>
    </div>

    <!-- Filtros -->
    <div class="mt-5 flex flex-col gap-3 lg:flex-row lg:items-center">
        <flux:radio.group wire:model.live="status" variant="segmented">
            <flux:radio value="active">Activos</flux:radio>
            <flux:radio value="discharged">Dados de baja</flux:radio>
            <flux:radio value="todos">Todos</flux:radio>
        </flux:radio.group>

        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Buscar por nombre..." />
    </div>

    <!-- Tabla -->
    <div class="mt-5 overflow-x-auto rounded-2xl border border-zinc-200 dark:border-zinc-800">
        <table class="w-full min-w-[52rem] text-sm">
            <thead class="bg-zinc-50 text-xs text-zinc-500 dark:bg-zinc-800/60">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold">Bien</th>
                    <th class="px-4 py-3 text-left font-semibold">Tipo</th>
                    <th class="px-4 py-3 text-right font-semibold">Cantidad</th>
                    <th class="px-4 py-3 text-right font-semibold">Costo unit.</th>
                    <th class="px-4 py-3 text-right font-semibold">Valor unit.</th>
                    <th class="px-4 py-3 text-right font-semibold">Valor total</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($assets as $asset)
                    <tr wire:key="asset-{{ $asset->id }}" @class(['opacity-60' => $asset->status === 'discharged'])>
                        <td class="px-4 py-3">
                            <p class="font-medium text-zinc-900 dark:text-white">{{ $asset->name }}</p>
                            <div class="mt-1 flex flex-wrap gap-1">
                                @if ($asset->status === 'discharged')
                                    <flux:badge size="sm" color="zinc">Dado de baja</flux:badge>
                                @endif
                                @if ($asset->sale_price !== null)
                                    <flux:badge size="sm" color="emerald">Se vende a S/ {{ number_format($asset->sale_price, 2) }}</flux:badge>
                                @endif
                                @if ($asset->delivers_with_sale)
                                    <flux:badge size="sm" color="sky">
                                        Se entrega con la venta{{ $asset->categories->isNotEmpty() ? ': '.$asset->categories->pluck('name')->join(', ') : '' }}
                                    </flux:badge>
                                @elseif ($asset->is_consumable)
                                    <flux:badge size="sm" color="amber">Consumible</flux:badge>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ \App\Models\Asset::TYPES[$asset->type] ?? $asset->type }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $asset->quantity }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-zinc-500">S/ {{ number_format($asset->unit_cost, 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">S/ {{ number_format($asset->current_unit_value, 2) }}</td>
                        <td class="px-4 py-3 text-right font-medium tabular-nums">S/ {{ number_format($asset->totalValue(), 2) }}</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-1">
                                <flux:button size="sm" variant="ghost" icon="plus-circle" wire:click="openStock({{ $asset->id }})" aria-label="Ingresar unidades" title="Ingresar unidades" />
                                @if ($asset->quantity > 0)
                                    <flux:button size="sm" variant="ghost" icon="arrow-down-tray" wire:click="openDischarge({{ $asset->id }})" aria-label="Dar de baja" title="Dar de baja" />
                                @endif
                                <flux:button size="sm" variant="ghost" icon="clock" wire:click="openHistory({{ $asset->id }})" aria-label="Historial" title="Historial" />
                                <flux:button size="sm" variant="ghost" icon="pencil" wire:click="edit({{ $asset->id }})" aria-label="Editar" title="Editar" />
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $asset->id }})"
                                    wire:confirm="¿Eliminar «{{ $asset->name }}»? Quedará registrado quién lo eliminó." aria-label="Eliminar" title="Eliminar" />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-zinc-500">No hay bienes registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Formulario del bien -->
    <flux:modal wire:model="showForm" class="w-full max-w-lg">
        <form wire:submit="save" class="flex flex-col gap-5">
            <flux:heading size="lg">{{ $assetId ? 'Editar bien' : 'Nuevo bien' }}</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="name" label="Nombre" placeholder="Maniquí de cuerpo entero" required class="sm:col-span-2" />
                <flux:select wire:model="type" label="Tipo">
                    @foreach (\App\Models\Asset::TYPES as $value => $label)
                        <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="quantity" type="number" min="0" label="Cantidad" :disabled="(bool) $assetId" />
                <flux:input wire:model="unit_cost" type="number" step="0.01" min="0" label="Costo de compra (unid.)" :disabled="(bool) $assetId" />
                <flux:input wire:model="current_unit_value" type="number" step="0.01" min="0" label="Valor actual (unid.)"
                    :placeholder="$assetId ? '' : 'Igual al costo'" />
                <flux:input wire:model="sale_price" type="number" step="0.01" min="0" label="Precio de venta (inc. IGV)"
                    placeholder="Vacío = no se vende" />
            </div>

            @unless ($assetId)
                <flux:switch wire:model="paidFromCash" label="Pagado desde caja" align="left"
                    description="Descuenta de caja cantidad × costo. Desmárcalo si el bien ya lo tenías." />
            @endunless

            @if ($assetId)
                <flux:text class="text-xs">La cantidad y el costo de compra cambian con «Ingresar unidades» y «Dar de baja».</flux:text>
            @endif

            <flux:switch wire:model.live="is_consumable" label="Consumible (se gasta, ej. portaternos o correas)" align="left" />

            @if ($is_consumable)
                <flux:switch wire:model.live="delivers_with_sale" label="Preguntar al vender si se entrega" align="left" />

                @if ($delivers_with_sale)
                    <flux:checkbox.group wire:model="category_ids" label="Se entrega con productos de" description="Si no marcas ninguna, se ofrece en todas las ventas.">
                        <div class="grid grid-cols-2 gap-2">
                            @foreach ($categories as $category)
                                <flux:checkbox :value="$category->id" :label="$category->name" />
                            @endforeach
                        </div>
                    </flux:checkbox.group>
                @endif
            @endif

            <flux:textarea wire:model="notes" label="Notas" rows="2" />

            <div class="flex justify-end gap-2 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                <flux:modal.close>
                    <flux:button variant="subtle">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Guardar</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Ingreso de unidades -->
    <flux:modal wire:model="showStock" class="w-full max-w-md">
        <form wire:submit="addStock" class="flex flex-col gap-5">
            <div>
                <flux:heading size="lg">Ingresar unidades</flux:heading>
                <flux:text class="mt-1">{{ $selected?->name }} · hay {{ $selected?->quantity }}. El costo se promedia con lo que ya tienes.</flux:text>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="movementQuantity" type="number" min="1" label="Cantidad" required />
                <flux:input wire:model="movementCost" type="number" step="0.01" min="0" label="Costo unitario" required />
            </div>
            <flux:input wire:model="movementNote" label="Nota" placeholder="Compra en el mercado" />
            <flux:switch wire:model="paidFromCash" label="Pagado desde caja" align="left"
                description="Descuenta de caja cantidad × costo unitario." />
            <div class="flex justify-end gap-2 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                <flux:modal.close>
                    <flux:button variant="subtle">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Ingresar</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Baja -->
    <flux:modal wire:model="showDischarge" class="w-full max-w-md">
        <form wire:submit="discharge" class="flex flex-col gap-5">
            <div>
                <flux:heading size="lg">Dar de baja</flux:heading>
                <flux:text class="mt-1">{{ $selected?->name }} · hay {{ $selected?->quantity }}.</flux:text>
            </div>
            <flux:input wire:model="movementQuantity" type="number" min="1" :max="$selected?->quantity" label="Cantidad" required />
            <flux:input wire:model="movementNote" label="Motivo" placeholder="Malogrado" required />
            <div class="flex justify-end gap-2 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                <flux:modal.close>
                    <flux:button variant="subtle">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger">Dar de baja</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Historial -->
    <flux:modal wire:model="showHistory" class="w-full max-w-2xl">
        <flux:heading size="lg">Historial · {{ $selected?->name }}</flux:heading>
        <div class="mt-4 overflow-x-auto">
            <table class="w-full min-w-[32rem] text-sm">
                <thead class="text-xs text-zinc-500">
                    <tr>
                        <th class="py-2 text-left font-semibold">Fecha</th>
                        <th class="py-2 text-left font-semibold">Movimiento</th>
                        <th class="py-2 text-right font-semibold">Cant.</th>
                        <th class="py-2 text-right font-semibold">S/ unid.</th>
                        <th class="py-2 pl-4 text-left font-semibold">Detalle</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($history as $movement)
                        <tr wire:key="movement-{{ $movement->id }}">
                            <td class="py-2 text-zinc-500">{{ $movement->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="py-2">{{ \App\Models\AssetMovement::TYPES[$movement->type] ?? $movement->type }}</td>
                            <td class="py-2 text-right tabular-nums">{{ $movement->quantity > 0 ? '+' : '' }}{{ $movement->quantity }}</td>
                            <td class="py-2 text-right tabular-nums">{{ number_format($movement->unit_cost, 2) }}</td>
                            <td class="py-2 pl-4 text-xs text-zinc-500">{{ $movement->note }} · {{ $movement->user?->name ?? 'Sistema' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-6 text-center text-zinc-500">Sin movimientos.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:modal>
</div>
