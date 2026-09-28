<div>
    <flux:modal wire:model="isOpen" class="w-full max-w-xl">
        <div class="flex flex-col gap-5">
            <div class="border-b border-zinc-100 pb-4 dark:border-zinc-800">
                <div class="mb-2 flex items-center gap-2 text-xs font-semibold text-violet-600 dark:text-violet-400">
                    <span class="grid size-6 place-items-center rounded-lg bg-violet-100 dark:bg-violet-950/60">
                        <flux:icon icon="wrench-screwdriver" class="size-3.5 text-violet-600 dark:text-violet-400" />
                    </span>
                    Nuevo registro
                </div>
                <h2 class="text-2xl font-semibold text-zinc-900 dark:text-white">Registrar mantenimiento</h2>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                    El costo se suma al historial de la prenda y a los gastos de mantenimiento del mes.
                </p>
            </div>

            <form wire:submit="save" class="flex flex-col gap-5">
                <!-- Prenda -->
                <div>
                    <flux:label>Prenda</flux:label>
                    @if ($this->unit)
                        @php($unit = $this->unit)
                        <div class="mt-1 flex items-center justify-between gap-3 rounded-xl border border-zinc-200 px-3 py-2 dark:border-zinc-700">
                            <div class="min-w-0">
                                <p class="font-mono text-sm font-semibold text-zinc-900 dark:text-white">{{ $unit->code }}</p>
                                <p class="truncate text-xs text-zinc-500">{{ $unit->variant?->product?->name }} · {{ $unit->variant?->label() }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <flux:badge size="sm" :color="\App\Models\PhysicalProduct::STATUS_COLORS[$unit->status] ?? 'zinc'">
                                    {{ \App\Models\PhysicalProduct::STATUS_LABELS[$unit->status] ?? $unit->status }}
                                </flux:badge>
                                <flux:button size="sm" variant="subtle" icon="x-mark" wire:click="clearUnit" aria-label="Cambiar prenda" />
                            </div>
                        </div>
                    @else
                        <div class="relative mt-1">
                            <flux:input wire:model.live.debounce.250ms="search" icon="qr-code" placeholder="Código de la prenda..." autocomplete="off" />
                            @if (mb_strlen(trim($search)) >= 2)
                                <div class="absolute z-20 mt-1 w-full rounded-xl border border-zinc-200 bg-white p-1 shadow-xl dark:border-zinc-700 dark:bg-zinc-900">
                                    @forelse ($this->results as $result)
                                        <button type="button" wire:key="maintenance-unit-{{ $result->id }}" wire:click="selectUnit({{ $result->id }})"
                                            class="flex w-full items-center justify-between gap-3 rounded-lg p-2 text-left hover:bg-zinc-50 dark:hover:bg-zinc-800">
                                            <span class="min-w-0">
                                                <span class="block font-mono text-sm font-medium">{{ $result->code }}</span>
                                                <span class="block truncate text-xs text-zinc-500">{{ $result->variant?->product?->name }} · {{ $result->variant?->label() }}</span>
                                            </span>
                                            <flux:badge size="sm" :color="\App\Models\PhysicalProduct::STATUS_COLORS[$result->status] ?? 'zinc'">
                                                {{ \App\Models\PhysicalProduct::STATUS_LABELS[$result->status] ?? $result->status }}
                                            </flux:badge>
                                        </button>
                                    @empty
                                        <p class="px-3 py-2 text-sm text-zinc-500">Sin prendas con ese código.</p>
                                    @endforelse
                                </div>
                            @endif
                        </div>
                    @endif
                    <flux:error name="unitId" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:select wire:model="type" label="Tipo">
                        @foreach ($types as $value => $label)
                            <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model="cost" type="number" step="0.01" min="0" label="Costo (S/)" required />
                    <flux:input wire:model="maintenance_date" type="date" label="Fecha" required />
                    <flux:input wire:model="provider_name" label="Proveedor / responsable" placeholder="Lavandería Express, nosotros..." />
                    <div class="sm:col-span-2">
                        <flux:textarea wire:model="description" label="Detalle" rows="2" placeholder="Lavado en seco, cambio de cierre..." />
                    </div>
                </div>

                @if ($this->unit && in_array($this->unit->status, ['available', 'maintenance']))
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-zinc-400">Después de registrar</p>
                        <flux:radio.group wire:model="after_status" variant="segmented" size="sm">
                            <flux:radio value="keep">Sin cambios</flux:radio>
                            <flux:radio value="maintenance">En mantenimiento</flux:radio>
                            <flux:radio value="available">Disponible</flux:radio>
                        </flux:radio.group>
                    </div>
                @endif

                <div class="flex flex-col-reverse gap-3 border-t border-zinc-100 pt-5 sm:flex-row sm:justify-end dark:border-zinc-800">
                    <flux:modal.close>
                        <flux:button variant="subtle">Cancelar</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">
                        <span wire:loading.remove wire:target="save">Guardar mantenimiento</span>
                        <span wire:loading wire:target="save">Guardando...</span>
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
