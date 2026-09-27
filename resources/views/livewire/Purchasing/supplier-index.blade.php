<div class="mx-auto max-w-7xl space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl" class="text-zinc-900 dark:text-white">Proveedores</flux:heading>
            <flux:subheading class="text-zinc-500 dark:text-zinc-400">Directorio de empresas y proveedores registrados</flux:subheading>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
            Nuevo Proveedor
        </flux:button>
    </div>

    <div class="flex items-center justify-between gap-4">
        <div class="w-72">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Buscar por código, RUC/DNI o nombre..." icon="magnifying-glass" />
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <table class="w-full text-left text-sm text-zinc-600 dark:text-zinc-400">
            <thead class="bg-zinc-50 text-xs font-semibold uppercase text-zinc-500 dark:bg-zinc-800/50 dark:text-zinc-400">
                <tr>
                    <th class="px-4 py-3">Código</th>
                    <th class="px-4 py-3">Proveedor / Razón Social</th>
                    <th class="px-4 py-3">Documento</th>
                    <th class="px-4 py-3">Contacto</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                @forelse ($suppliers as $supplier)
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                        <td class="px-4 py-3 font-mono text-xs font-bold text-zinc-900 dark:text-white">{{ $supplier->code }}</td>
                        <td class="px-4 py-3 font-medium text-zinc-900 dark:text-white">{{ $supplier->display_name }}</td>
                        <td class="px-4 py-3"><span class="font-bold">{{ $supplier->document_type }}:</span> {{ $supplier->document_number }}</td>
                        <td class="px-4 py-3">{{ $supplier->mobile ?? $supplier->phone ?? $supplier->email ?? '-' }}</td>
                        <td class="px-4 py-3">
                            <flux:badge :color="$supplier->is_active ? 'emerald' : 'zinc'" size="sm">
                                {{ $supplier->is_active ? 'Activo' : 'Inactivo' }}
                            </flux:badge>
                        </td>
                        <td class="px-4 py-3 text-right space-x-1">
                            <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="openEditModal({{ $supplier->id }})" />
                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $supplier->id }})" wire:confirm="¿Desea eliminar este proveedor?" />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-zinc-400">No se encontraron proveedores.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="p-4 border-t border-zinc-200 dark:border-zinc-800">
            {{ $suppliers->links() }}
        </div>
    </div>

    <livewire:purchasing.supplier-modal />
</div>