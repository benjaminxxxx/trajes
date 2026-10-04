<flux:modal wire:model="show" class="w-full max-w-xl">
    <div class="flex flex-col gap-5">
        <!-- Header -->
        <div class="border-b border-zinc-100 pb-4 dark:border-zinc-800">
            <div class="mb-2 flex items-center gap-2 text-xs font-semibold text-violet-600 dark:text-violet-400">
                <span class="grid size-6 place-items-center rounded-lg bg-violet-100 dark:bg-violet-950/60">
                    <flux:icon :icon="$supplierId ? 'pencil-square' : 'plus'" class="size-3.5 text-violet-600 dark:text-violet-400" />
                </span>
                {{ $supplierId ? 'Editar registro' : 'Nuevo registro' }}
            </div>
            <h2 class="text-2xl font-semibold text-zinc-900 dark:text-white">
                {{ $supplierId ? 'Editar proveedor' : 'Agregar proveedor' }}
            </h2>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                {{ $supplierId ? 'Actualiza los datos del proveedor.' : 'Empieza con lo esencial, puedes completar el resto después.' }}
            </p>
        </div>

        <form wire:submit="save" class="flex flex-col gap-5">
            <!-- Tipo de persona -->
            <div>
                <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-400">Tipo de persona</p>
                <div class="grid grid-cols-2 gap-3">
                    @foreach (['company' => ['Persona jurídica', 'building-office'], 'individual' => ['Persona natural', 'user']] as $value => [$label, $icon])
                        <button type="button" wire:click="setType('{{ $value }}')" @class([
                            'flex items-center gap-3 rounded-2xl border p-3 text-left transition-all',
                            'border-violet-500 bg-violet-50/50 dark:border-violet-500/60 dark:bg-violet-950/30' => $type === $value,
                            'border-zinc-200 hover:border-zinc-300 dark:border-zinc-800 dark:hover:border-zinc-700' => $type !== $value,
                        ])>
                            <flux:icon :icon="$icon" class="size-4 text-violet-600 dark:text-violet-400" />
                            <span class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">{{ $label }}</span>
                            @if ($type === $value)
                                <flux:icon icon="check" class="ml-auto size-4 text-violet-600 dark:text-violet-400" />
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>

            <!-- Documento -->
            <div class="grid gap-4 sm:grid-cols-[140px_1fr]">
                <flux:select wire:model="document_type" label="Documento">
                    @if ($type === 'company')
                        <flux:select.option value="RUC">RUC</flux:select.option>
                    @else
                        <flux:select.option value="DNI">DNI</flux:select.option>
                        <flux:select.option value="RUC">RUC (10...)</flux:select.option>
                        <flux:select.option value="CE">CE</flux:select.option>
                        <flux:select.option value="PASAPORTE">Pasaporte</flux:select.option>
                    @endif
                </flux:select>

                <flux:field>
                    <flux:label>Número de documento</flux:label>
                    <div class="flex items-start gap-2">
                        <div class="min-w-0 flex-1">
                            <flux:input wire:model="document_number" inputmode="numeric"
                                :placeholder="$type === 'company' ? '20607845129' : '72849103'" required
                                x-on:keydown.enter.prevent="$wire.document_type === 'RUC' ? $wire.lookupRuc() : null" />
                        </div>
                        <flux:button type="button" icon="magnifying-glass" variant="filled" wire:click="lookupRuc"
                            wire:loading.attr="disabled" wire:target="lookupRuc"
                            x-show="$wire.document_type === 'RUC'" title="Buscar RUC en SUNAT">
                            <span wire:loading.remove wire:target="lookupRuc">Buscar</span>
                            <span wire:loading wire:target="lookupRuc">Buscando...</span>
                        </flux:button>
                    </div>
                    <flux:error name="document_number" />
                </flux:field>
            </div>

            <!-- Nombre -->
            @if ($type === 'company')
                <flux:input wire:model="company_name" label="Razón social" placeholder="Confecciones Gamarra S.A.C." required />
            @else
                <div class="grid gap-4 sm:grid-cols-3">
                    <flux:input wire:model="names" label="Nombres" required />
                    <flux:input wire:model="paternal_last_name" label="Apellido paterno" required />
                    <flux:input wire:model="maternal_last_name" label="Apellido materno" />
                </div>
            @endif

            <!-- Toggle datos adicionales -->
            <button type="button" wire:click="$toggle('advanced')"
                class="flex items-center justify-between rounded-2xl border border-dashed border-zinc-200 bg-zinc-50/50 p-4 text-left transition-colors dark:border-zinc-800 dark:bg-zinc-800/40">
                <div>
                    <span class="block text-sm font-semibold text-zinc-800 dark:text-zinc-200">
                        {{ $advanced ? 'Datos adicionales' : '¿Quieres agregar más datos?' }}
                    </span>
                    <span class="mt-0.5 block text-xs text-zinc-500 dark:text-zinc-400">Contacto, dirección, cuenta bancaria y notas</span>
                </div>
                <flux:icon :icon="$advanced ? 'chevron-up' : 'document-text'" class="size-5 text-violet-600 dark:text-violet-400" />
            </button>

            @if ($advanced)
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="mobile" type="tel" label="Celular" placeholder="987 654 321" icon="phone" />
                    <flux:input wire:model="phone" label="Teléfono fijo" />
                    <div class="sm:col-span-2">
                        <flux:input wire:model="email" type="email" label="Correo" icon="envelope" />
                    </div>
                    <div class="sm:col-span-2">
                        <flux:input wire:model="address" label="Dirección fiscal" icon="map-pin" />
                    </div>

                    <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 sm:col-span-2">Datos bancarios para pagos</p>
                    <flux:input wire:model="bank_name" label="Banco" placeholder="BCP, Interbank..." />
                    <flux:input wire:model="account_number" label="N° de cuenta" />
                    <div class="sm:col-span-2">
                        <flux:input wire:model="cci" label="CCI" />
                    </div>

                    <div class="sm:col-span-2">
                        <flux:textarea wire:model="notes" label="Notas internas" rows="2" />
                    </div>
                    <div class="sm:col-span-2">
                        <flux:switch wire:model="is_active" label="Proveedor activo" align="left" />
                    </div>
                </div>
            @endif

            <!-- Footer -->
            <div class="flex flex-col-reverse gap-3 border-t border-zinc-100 pt-5 sm:flex-row sm:justify-end dark:border-zinc-800">
                <flux:modal.close>
                    <flux:button variant="subtle">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">
                    <span wire:loading.remove wire:target="save">{{ $supplierId ? 'Guardar cambios' : 'Guardar proveedor' }}</span>
                    <span wire:loading wire:target="save">Guardando...</span>
                </flux:button>
            </div>
        </form>
    </div>
</flux:modal>
