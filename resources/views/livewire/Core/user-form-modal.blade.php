<flux:modal wire:model="isUserFormOpen" class="max-w-xl">
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
                {{ $editingId ? 'Editar usuario' : 'Agregar usuario' }}
            </h2>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                {{ $editingId ? 'Actualiza los datos y el rol del usuario.' : 'Crea un acceso para un administrador o vendedor.' }}
            </p>
        </div>

        <!-- Actividad del vendedor -->
        @if ($editingId && $role === 'vendedor')
            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                    <flux:text class="text-xs">Alquileres realizados</flux:text>
                    <flux:heading size="xl" class="mt-1">{{ $rentalsCount }}</flux:heading>
                </div>
                <div class="rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                    <flux:text class="text-xs">Ventas realizadas</flux:text>
                    <flux:heading size="xl" class="mt-1">{{ $salesCount }}</flux:heading>
                </div>
            </div>
        @endif

        <form wire:submit="save" class="flex flex-col gap-5">
            <!-- Selector de Rol -->
            <div>
                <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-400">
                    Rol
                </p>
                <div class="grid grid-cols-2 gap-3">
                    @foreach (['vendedor' => ['Vendedor', 'shopping-bag'], 'admin' => ['Administrador', 'shield-check']] as $value => [$label, $icon])
                        <button type="button" wire:click="$set('role', '{{ $value }}')"
                            @class([
                                'flex items-center gap-3 rounded-2xl border p-3 text-left transition-all',
                                'border-violet-500 bg-violet-50/50 dark:border-violet-500/60 dark:bg-violet-950/30' => $role === $value,
                                'border-zinc-200 hover:border-zinc-300 dark:border-zinc-800 dark:hover:border-zinc-700' => $role !== $value,
                            ])>
                            <flux:icon :icon="$icon" class="size-4 text-violet-600 dark:text-violet-400" />
                            <span class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">{{ $label }}</span>
                            @if ($role === $value)
                                <flux:icon icon="check" class="ml-auto size-4 text-violet-600 dark:text-violet-400" />
                            @endif
                        </button>
                    @endforeach
                </div>
                <flux:error name="role" class="mt-2" />
            </div>

            <flux:input wire:model="name" label="Nombre" placeholder="Ana Morales" required />

            <flux:input wire:model="email" type="email" label="Correo" placeholder="ana@correo.com" icon="envelope"
                required />

            <flux:input wire:model="password" type="password" viewable label="Contraseña"
                :description="$editingId ? 'Déjalo vacío para mantener la contraseña actual.' : null"
                :required="!$editingId" />

            <flux:switch wire:model="is_active" label="Usuario activo" align="left" />

            <!-- Footer y Acciones -->
            <div
                class="flex flex-col-reverse gap-3 border-t border-zinc-100 pt-5 sm:flex-row sm:items-center sm:justify-between dark:border-zinc-800">
                <div>
                    @if ($editingId && !$this->isEditingSelf())
                        <flux:button wire:click="deleteUser"
                            wire:confirm="¿Eliminar este usuario? Si tiene movimientos registrados, será dado de baja."
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
                            {{ $editingId ? 'Guardar cambios' : 'Guardar usuario' }}
                        </span>
                        <span wire:loading wire:target="save">Guardando...</span>
                    </flux:button>
                </div>
            </div>
        </form>
    </div>
</flux:modal>
