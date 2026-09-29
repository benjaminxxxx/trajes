<div>
    <flux:modal wire:model="isOpen" class="w-full max-w-3xl">
        <div class="flex flex-col gap-5">
            <div class="border-b border-zinc-100 pb-4 dark:border-zinc-800">
                <div class="mb-2 flex items-center gap-2 text-xs font-semibold text-violet-600 dark:text-violet-400">
                    <span class="grid size-6 place-items-center rounded-lg bg-violet-100 dark:bg-violet-950/60">
                        <flux:icon icon="bookmark" class="size-3.5 text-violet-600 dark:text-violet-400" />
                    </span>
                    Separaciones
                </div>
                <h2 class="text-2xl font-semibold text-zinc-900 dark:text-white">Prendas separadas</h2>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                    Son solo una marca: si alguien llega antes, la prenda se puede vender o alquilar igual.
                </p>
            </div>

            <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
                <table class="w-full min-w-[40rem] text-sm">
                    <thead class="bg-zinc-50 text-xs text-zinc-500 dark:bg-zinc-800/60">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold">Prenda</th>
                            <th class="px-3 py-2 text-left font-semibold">Cliente</th>
                            <th class="px-3 py-2 text-left font-semibold">Hasta</th>
                            <th class="px-3 py-2 text-left font-semibold">Estado</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse ($this->reservations as $unit)
                            <tr wire:key="reservation-{{ $unit->id }}">
                                <td class="px-3 py-2">
                                    <p class="font-medium text-zinc-800 dark:text-zinc-200">{{ $unit->variant?->product?->name }}</p>
                                    <p class="font-mono text-xs text-zinc-500">{{ $unit->code }} · {{ $unit->variant?->label() }}</p>
                                </td>
                                <td class="px-3 py-2">
                                    <p>{{ $unit->reservedClient?->display_name }}</p>
                                    @if ($unit->reservation_note)
                                        <p class="text-xs text-zinc-500">{{ $unit->reservation_note }}</p>
                                    @endif
                                </td>
                                <td class="px-3 py-2">
                                    <span @class(['font-medium', 'text-red-600 dark:text-red-400' => $unit->isReservationExpired()])>
                                        {{ $unit->reserved_until?->format('d/m/Y') }}
                                    </span>
                                    @if ($unit->isReservationExpired())
                                        <flux:badge size="sm" color="red" class="ms-1">Vencida</flux:badge>
                                    @endif
                                </td>
                                <td class="px-3 py-2">
                                    <flux:badge size="sm" :color="\App\Models\PhysicalProduct::STATUS_COLORS[$unit->status] ?? 'zinc'">
                                        {{ \App\Models\PhysicalProduct::STATUS_LABELS[$unit->status] ?? $unit->status }}
                                    </flux:badge>
                                </td>
                                <td class="px-3 py-2 text-right">
                                    @can('alquileres.registrar')
                                        <flux:button size="sm" variant="subtle" icon="x-mark" wire:click="release({{ $unit->id }})"
                                            wire:confirm="¿Liberar la prenda {{ $unit->code }}?">
                                            Liberar
                                        </flux:button>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-3 py-10 text-center text-zinc-500">
                                    No hay prendas separadas. Sepáralas desde «Nuevo alquiler» con el botón «Solo separar prendas».
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="flex justify-end">
                <flux:modal.close>
                    <flux:button variant="subtle">Cerrar</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>
