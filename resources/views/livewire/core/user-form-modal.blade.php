<flux:modal wire:model="isUserFormOpen" class="max-w-xl">
    <div class="flex flex-col gap-5">
        <!-- Header -->
        <div class="border-b border-zinc-100 pb-4 dark:border-zinc-800">
            <div class="mb-2 flex items-center gap-2 text-xs font-semibold text-violet-600 dark:text-violet-400">
                <span class="grid size-6 place-items-center rounded-lg bg-violet-100 dark:bg-violet-950/60">
                    <flux:icon :icon="$editingId ? 'pencil-square' : 'plus'"
                        class="size-3.5 text-violet-600 dark:text-violet-400" />
                </span>
                <span>{{ $editingId ? 'Acceso al negocio' : 'Nuevo acceso' }}</span>
            </div>
            <h2 class="flex items-center gap-2 text-2xl font-semibold text-zinc-900 dark:text-white">
                {{ $editingId ? $name : 'Agregar persona' }}
                @if ($isOwner)
                    <flux:badge size="sm" color="amber" icon="star">Dueño</flux:badge>
                @endif
            </h2>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                {{ $editingId
                    ? $email
                    : 'Si la persona ya usa Kipusoft (en otro negocio), entra con su misma cuenta. Si es nueva, le llega un correo para crear su contraseña.' }}
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

        @if ($isOwner)
            <flux:callout icon="shield-check" variant="secondary">
                <flux:callout.text>El dueño siempre es administrador: nadie puede cambiar su rol ni retirarlo.</flux:callout.text>
            </flux:callout>
        @endif

        <form wire:submit="save" class="flex flex-col gap-5">
            @php($roleLocked = $isOwner || $this->isEditingSelf() || ($editingId && ! $isActiveMember))

            <!-- Selector de Rol -->
            <div>
                <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-400">Rol en este negocio</p>
                <div class="grid grid-cols-2 gap-3">
                    @foreach (['vendedor' => ['Vendedor', 'shopping-bag'], 'admin' => ['Administrador', 'shield-check']] as $value => [$label, $icon])
                        <button type="button" wire:click="$set('role', '{{ $value }}')" @disabled($roleLocked)
                            @class([
                                'flex items-center gap-3 rounded-2xl border p-3 text-left transition-all disabled:cursor-not-allowed disabled:opacity-60',
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

            @unless ($editingId)
                <flux:input wire:model="name" label="Nombre" placeholder="Ana Morales" required />
                <flux:input wire:model="email" type="email" label="Correo" placeholder="ana@correo.com" icon="envelope" required />
            @endunless

            <!-- Footer y Acciones -->
            <div class="flex flex-col-reverse gap-3 border-t border-zinc-100 pt-5 sm:flex-row sm:items-center sm:justify-between dark:border-zinc-800">
                <div class="flex flex-wrap gap-2">
                    @if ($editingId && $isActiveMember && ! $isOwner && ! $this->isEditingSelf())
                        <flux:button wire:click="revoke"
                            wire:confirm="¿Retirar a {{ $name }} de este negocio? Ya no podrá entrar aquí; sus ventas y alquileres se conservan."
                            variant="danger" icon="user-minus">
                            Retirar del negocio
                        </flux:button>
                    @endif
                    @if ($this->canTransferOwnership())
                        <flux:button wire:click="transferOwnership"
                            wire:confirm="¿Transferir la propiedad del negocio a {{ $name }}? Tú seguirás como administrador."
                            variant="outline" icon="star">
                            Hacer dueño
                        </flux:button>
                    @endif
                </div>

                <div class="flex flex-col-reverse gap-3 sm:flex-row">
                    <flux:modal.close>
                        <flux:button variant="subtle">Cancelar</flux:button>
                    </flux:modal.close>

                    @unless ($roleLocked && $editingId)
                        <flux:button type="submit" variant="primary">
                            <span wire:loading.remove wire:target="save">{{ $editingId ? 'Guardar rol' : 'Dar acceso' }}</span>
                            <span wire:loading wire:target="save">Guardando...</span>
                        </flux:button>
                    @endunless
                </div>
            </div>
        </form>
    </div>
</flux:modal>
