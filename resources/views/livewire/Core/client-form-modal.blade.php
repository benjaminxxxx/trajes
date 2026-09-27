<flux:modal wire:model="isClientFormOpen" class="max-w-xl">
    <div x-data="clientFormAlpine">
        <!-- Header Sticky -->
        <div
            class="border-b border-zinc-100 dark:border-zinc-800">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="mb-2 flex items-center gap-2 text-xs font-semibold text-violet-600 dark:text-violet-400">
                        <span class="grid size-6 place-items-center rounded-lg bg-violet-100 dark:bg-violet-950/60">
                            <flux:icon icon="plus" class="size-3.5 text-violet-600 dark:text-violet-400" />
                        </span>
                        <span x-text="$wire.editingId ? 'Editar registro' : 'Nuevo registro'"></span>
                    </div>
                    <h2 class="text-2xl font-semibold text-zinc-900 dark:text-white">
                        <span x-text="$wire.editingId ? 'Editar cliente' : 'Agregar cliente'"></span>
                    </h2>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                        <span
                            x-text="$wire.editingId ? 'Actualiza los datos del cliente.' : 'Empieza con lo esencial, puedes editarlo después.'"></span>
                    </p>
                </div>
            </div>
        </div>
        <!-- Formulario -->
        <form wire:submit="save" class="flex flex-col gap-5">

            <!-- Selector Tipo de Cliente -->
            <div>
                <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-400">
                    Tipo de cliente
                </p>
                <div class="grid grid-cols-2 gap-3">
                    <button type="button" x-on:click="setType('individual')"
                        :class="type === 'individual' ?
                            'border-violet-500 bg-violet-50/50 dark:bg-violet-950/30 dark:border-violet-500/60' :
                            'border-zinc-200 dark:border-zinc-800 hover:border-zinc-300 dark:hover:border-zinc-700'"
                        class="flex items-center gap-3 rounded-2xl border p-3 text-left transition-all">
                        <flux:icon icon="user" class="size-4 text-violet-600 dark:text-violet-400" />
                        <span class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">Persona</span>
                        <template x-if="type === 'individual'">
                            <flux:icon icon="check" class="ml-auto size-4 text-violet-600 dark:text-violet-400" />
                        </template>
                    </button>

                    <button type="button" x-on:click="setType('company')"
                        :class="type === 'company' ?
                            'border-violet-500 bg-violet-50/50 dark:bg-violet-950/30 dark:border-violet-500/60' :
                            'border-zinc-200 dark:border-zinc-800 hover:border-zinc-300 dark:hover:border-zinc-700'"
                        class="flex items-center gap-3 rounded-2xl border p-3 text-left transition-all">
                        <flux:icon icon="building-office" class="size-4 text-violet-600 dark:text-violet-400" />
                        <span class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">Compañía</span>
                        <template x-if="type === 'company'">
                            <flux:icon icon="check" class="ml-auto size-4 text-violet-600 dark:text-violet-400" />
                        </template>
                    </button>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-[140px_1fr]">
                <flux:select wire:model.live="document_type" label="Documento">
                    <template x-if="type === 'individual'">
                        <flux:select.option value="DNI">DNI</flux:select.option>
                    </template>
                    <template x-if="type === 'company'">
                        <flux:select.option value="RUC">RUC</flux:select.option>
                    </template>
                    <flux:select.option value="CE">CE</flux:select.option>
                    <flux:select.option value="PASAPORTE">Pasaporte</flux:select.option>
                </flux:select>


                <flux:input wire:model="document_number" label="Número de documento"
                    x-bind:placeholder="type === 'individual' ? '72849103' : '20607845129'" required />
            </div>
            <!-- Campos condicionales según tipo -->
            <template x-if="type === 'individual'">
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="names" label="Nombres" placeholder="Ana María" required />
                    <flux:input wire:model="last_name" label="Apellidos" placeholder="Morales" required />
                </div>
            </template>
            <template x-if="type === 'company'">
                <flux:input wire:model="company_name" label="Razón social" placeholder="Lumen Creativo S.A.C."
                    required />
            </template>

            <!-- Toggle Datos Adicionales -->
            <button type="button" x-on:click="advanced = !advanced"
                class="flex items-center justify-between rounded-2xl border border-dashed border-zinc-200 bg-zinc-50/50 p-4 text-left transition-colors dark:border-zinc-800 dark:bg-zinc-800/40">
                <div>
                    <span class="block text-sm font-semibold text-zinc-800 dark:text-zinc-200"
                        x-text="advanced ? 'Datos adicionales' : '¿Quieres agregar más datos?'"></span>
                    <span class="mt-0.5 block text-xs text-zinc-500 dark:text-zinc-400">Correo, teléfono, dirección y
                        más</span>
                </div>
                <flux:icon icon="document-text" class="size-5 text-violet-600 dark:text-violet-400" />
            </button>

            <!-- Sección Avanzada con Alpine Transition -->
            <div x-show="advanced" x-collapse class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="email" type="email" label="Correo" placeholder="ana@correo.com"
                    icon="envelope" />
                <flux:input wire:model="mobile" type="tel" label="Celular" placeholder="+51 999 999 999"
                    icon="phone" />

                <div class="sm:col-span-2">
                    <flux:input wire:model="address" label="Dirección" placeholder="Av. Principal 123" icon="map-pin" />
                </div>

                <template x-if="type === 'individual'">
                    <div class="grid gap-4 sm:col-span-2 sm:grid-cols-2">
                        <flux:input wire:model="birth_date" type="date" label="Fecha de Nacimiento" />
                        <flux:select wire:model="gender" label="Género">
                            <flux:select.option value="">Seleccionar...</flux:select.option>
                            <flux:select.option value="male">Masculino</flux:select.option>
                            <flux:select.option value="female">Femenino</flux:select.option>
                            <flux:select.option value="other">Otro</flux:select.option>
                        </flux:select>
                    </div>
                </template>

                <div class="sm:col-span-2">
                    <flux:textarea wire:model="notes" label="Notas adicionales"
                        placeholder="Detalles de facturación o preferencias..." rows="2" />
                </div>
            </div>

            <!-- Footer y Acciones -->
            <div
                class="flex flex-col-reverse gap-3 border-t border-zinc-100 pt-5 dark:border-zinc-800 sm:flex-row sm:justify-end">
                <flux:modal.close>
                    <flux:button variant="subtle">Cancelar</flux:button>
                </flux:modal.close>

                <flux:button type="submit" variant="primary"
                    class="bg-zinc-950 text-white hover:bg-zinc-800 dark:bg-white dark:text-zinc-950 dark:hover:bg-zinc-200">
                    <span wire:loading.remove wire:target="save">
                        <span x-text="$wire.editingId ? 'Guardar cambios' : 'Guardar cliente'"></span>
                    </span>
                    <span wire:loading wire:target="save">Guardando...</span>
                </flux:button>
            </div>
        </form>
    </div>
</flux:modal>
@script
    <script>
        Alpine.data('clientFormAlpine', () => ({
            type: @entangle('type'),
            advanced: @entangle('advanced'),
            init() {

            },
            setType(value) {
                this.type = value;
                if (value === 'individual' && this.$wire.document_type === 'RUC') {
                    this.$wire.document_type = 'DNI';
                } else if (value === 'company' && this.$wire.document_type === 'DNI') {
                    this.$wire.document_type = 'RUC';
                }
            }
        }));
    </script>
@endscript
