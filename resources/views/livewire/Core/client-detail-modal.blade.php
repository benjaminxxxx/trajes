<flux:modal wire:model="show" class="md:max-w-lg space-y-6">
    @if ($client)
        <!-- Header del Modal -->
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-violet-600 dark:text-violet-400">
                Ficha del cliente
            </p>

            <flux:heading size="lg" class="mt-1">
                {{ $client->display_name }}
            </flux:heading>

            <flux:subheading class="text-xs">
                <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $client->document_type }}:</span>
                {{ $client->document_number }}
            </flux:subheading>
        </div>

        <!-- Muestras de Métricas (Grid de 3 columnas) -->
        <div class="grid grid-cols-3 gap-3">
            <flux:card variant="soft" class="p-3 text-center">
                <flux:heading size="xl" class="font-bold">
                    {{ $client->sales_count ?? 0 }}
                </flux:heading>
                <flux:text class="mt-0.5 text-xs">Ventas</flux:text>
            </flux:card>

            <flux:card variant="soft" class="p-3 text-center">
                <flux:heading size="xl" class="font-bold">
                    {{ $client->rentals_count ?? 0 }}
                </flux:heading>
                <flux:text class="mt-0.5 text-xs">Alquileres</flux:text>
            </flux:card>

            <flux:card variant="soft" class="p-3 text-center">
                <flux:heading size="xl" class="font-bold">
                    {{ $client->invoices_count ?? 0 }}
                </flux:heading>
                <flux:text class="mt-0.5 text-xs">Facturas</flux:text>
            </flux:card>
        </div>

        <!-- Datos de contacto -->
        <div class="space-y-2.5 rounded-2xl border border-zinc-200/80 p-4 text-sm dark:border-zinc-800">
            <div class="flex items-center gap-2.5 text-zinc-700 dark:text-zinc-300">
                <flux:icon icon="envelope" class="size-4 text-violet-500 shrink-0" />
                <span class="truncate">{{ $client->email ?: 'Sin correo registrado' }}</span>
            </div>

            <div class="flex items-center gap-2.5 text-zinc-700 dark:text-zinc-300">
                <flux:icon icon="phone" class="size-4 text-violet-500 shrink-0" />
                <span>{{ $client->phone ?: 'Sin teléfono registrado' }}</span>
            </div>

            <div class="flex items-center gap-2.5 text-zinc-700 dark:text-zinc-300">
                <flux:icon icon="map-pin" class="size-4 text-violet-500 shrink-0" />
                <span class="truncate">{{ $client->address ?: 'Sin dirección registrada' }}</span>
            </div>
        </div>

        <!-- Acciones del Modal -->
        <div
            class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-2 border-t border-zinc-200/60 dark:border-zinc-800">
            <flux:button type="button" wire:click="archiveClient" wire:confirm="¿Deseas dar de baja a este cliente?"
                variant="danger" icon="trash">
                Dar de baja
            </flux:button>

            <flux:button type="button" wire:click="openEdit" variant="filled" icon="pencil">
                Editar cliente
            </flux:button>
        </div>
    @endif
</flux:modal>
