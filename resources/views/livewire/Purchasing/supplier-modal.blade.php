<flux:modal wire:model="show" class="md:max-w-3xl">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg" class="text-zinc-900 dark:text-white">
                {{ $supplierId ? 'Editar Proveedor' : 'Nuevo Proveedor' }}
            </flux:heading>
            <flux:subheading class="text-zinc-500 dark:text-zinc-400">
                Complete los datos fiscales y de contacto del proveedor
            </flux:subheading>
        </div>

        <form wire:submit="save" class="space-y-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <flux:select label="Tipo de Personería" wire:model.live="type">
                    <flux:select.option value="individual">Persona Natural</flux:select.option>
                    <flux:select.option value="company">Persona Jurídica</flux:select.option>
                </flux:select>

                <flux:select label="Tipo Doc." wire:model.live="document_type">
                    @if ($type === 'company')
                        <flux:select.option value="RUC">RUC</flux:select.option>
                    @else
                        <flux:select.option value="DNI">DNI</flux:select.option>
                        <flux:select.option value="RUC">RUC (10...)</flux:select.option>
                        <flux:select.option value="CE">Carné de Extranjería</flux:select.option>
                        <flux:select.option value="PASAPORTE">Pasaporte</flux:select.option>
                    @endif
                </flux:select>

                <flux:input label="N° Documento" wire:model="document_number" placeholder="10203040" required />
            </div>

            @if ($type === 'company')
                <div>
                    <flux:input label="Razón Social / Nombre Comercial" wire:model="company_name" placeholder="Inversiones S.A.C." required />
                </div>
            @else
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <flux:input label="Nombres" wire:model="names" required />
                    <flux:input label="Apellido Paterno" wire:model="paternal_last_name" required />
                    <flux:input label="Apellido Materno" wire:model="maternal_last_name" />
                </div>
            @endif

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <flux:input label="Celular" wire:model="mobile" placeholder="987654321" />
                <flux:input label="Teléfono Fijo" wire:model="phone" />
                <flux:input label="Correo Electrónico" type="email" wire:model="email" />
            </div>

            <div>
                <flux:input label="Dirección Fiscal / Ubicación" wire:model="address" />
            </div>

            <div class="rounded-lg border border-zinc-200 py-4 dark:border-zinc-800 space-y-3">
                <h4 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Datos Bancarios para Pagos</h4>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <flux:input label="Banco" wire:model="bank_name" placeholder="BCP, Interbank..." />
                    <flux:input label="N° Cuenta" wire:model="account_number" />
                    <flux:input label="CCI" wire:model="cci" />
                </div>
            </div>

            <div>
                <flux:textarea label="Notas internas" wire:model="notes" rows="2" />
            </div>

            <div class="flex items-center justify-between pt-4">
                <flux:switch wire:model="is_active" label="Estado Activo" />

                <div class="flex items-center gap-2">
                    <flux:button variant="filled" wire:click="$set('show', false)">Cancelar</flux:button>
                    <flux:button type="submit" variant="primary">
                        {{ $supplierId ? 'Actualizar' : 'Guardar Proveedor' }}
                    </flux:button>
                </div>
            </div>
        </form>
    </div>
</flux:modal>