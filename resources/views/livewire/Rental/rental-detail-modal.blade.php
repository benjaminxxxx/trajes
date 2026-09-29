<div>
    <flux:modal wire:model="isOpen" class="w-full max-w-3xl">
        @if ($isOpen && $this->rental)
            @php($rental = $this->rental)

            <div class="flex flex-col gap-5">
                <!-- Header -->
                <div class="border-b border-zinc-100 pb-4 dark:border-zinc-800">
                    <div class="mb-2 flex items-center gap-2 text-xs font-semibold text-violet-600 dark:text-violet-400">
                        <span class="grid size-6 place-items-center rounded-lg bg-violet-100 dark:bg-violet-950/60">
                            <flux:icon icon="sparkles" class="size-3.5 text-violet-600 dark:text-violet-400" />
                        </span>
                        Alquiler
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ $rental->code }}</h2>
                        @include('livewire.rental.partials.status-badge', ['rental' => $rental])
                    </div>
                    <div class="mt-1 flex flex-wrap items-center justify-between gap-2">
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">
                            Registrado por {{ $rental->user?->name ?? '—' }} el {{ $rental->created_at?->format('d/m/Y H:i') }}
                        </p>
                        <flux:button size="sm" icon="document-text" wire:navigate
                            :href="route('receipts.show', ['type' => 'alquiler', 'id' => $rental->id])">
                            Comprobante
                        </flux:button>
                    </div>
                </div>

                @if ($rental->isCancelled())
                    <flux:callout variant="danger" icon="x-circle"
                        heading="Anulado el {{ $rental->cancelled_at?->format('d/m/Y H:i') }} por {{ $rental->cancelledBy?->name ?? '—' }}">
                        <flux:callout.text>{{ $rental->cancellation_reason }}</flux:callout.text>
                    </flux:callout>
                @endif

                @if ($rental->status === \App\Models\Rental::STATUS_RETURNED)
                    <flux:callout variant="success" icon="check-circle"
                        heading="Recepcionado el {{ $rental->actual_return_date?->format('d/m/Y H:i') }}{{ (float) $rental->penalty_total > 0 ? ' · cobro por daños S/ ' . number_format($rental->penalty_total, 2) : '' }}">
                        @if ($rental->return_notes)
                            <flux:callout.text>{{ $rental->return_notes }}</flux:callout.text>
                        @endif
                    </flux:callout>
                @endif

                <!-- Prendas (solo lectura: afecta inventario) -->
                <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
                    <table class="w-full text-sm">
                        <thead class="bg-zinc-50 text-xs text-zinc-500 dark:bg-zinc-800/60">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold">Prenda</th>
                                <th class="px-3 py-2 text-left font-semibold">Código</th>
                                <th class="px-3 py-2 text-left font-semibold">Devolución</th>
                                <th class="px-3 py-2 text-right font-semibold">Precio</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($rental->details as $detail)
                                @php($variant = $detail->physicalProduct?->variant)
                                <tr wire:key="rental-detail-{{ $detail->id }}">
                                    <td class="px-3 py-2">
                                        <p class="font-medium text-zinc-800 dark:text-zinc-200">{{ $variant?->product?->name }}</p>
                                        <p class="text-xs text-zinc-500">
                                            {{ collect([$variant?->size?->name ? 'Talla ' . $variant->size->name : null, $variant?->color?->name])->filter()->join(' · ') }}
                                        </p>
                                    </td>
                                    <td class="px-3 py-2 text-xs text-zinc-600 dark:text-zinc-300">{{ $detail->physicalProduct?->code }}</td>
                                    <td class="px-3 py-2 text-xs text-zinc-600 dark:text-zinc-300">{{ ['good' => 'Buena', 'dirty' => 'Sucia', 'damaged' => 'Dañada', 'lost' => 'Perdida'][$detail->return_condition] ?? '—' }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ number_format($detail->price, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t border-zinc-200 text-sm dark:border-zinc-700">
                            <tr>
                                <td colspan="3" class="px-3 py-1.5 text-right text-zinc-500">Garantía</td>
                                <td class="px-3 py-1.5 text-right tabular-nums">{{ number_format($rental->deposit_amount, 2) }}</td>
                            </tr>
                            @if ((float) $rental->penalty_total > 0)
                                <tr>
                                    <td colspan="3" class="px-3 py-1.5 text-right text-zinc-500">Penalidades</td>
                                    <td class="px-3 py-1.5 text-right tabular-nums">{{ number_format($rental->penalty_total, 2) }}</td>
                                </tr>
                            @endif
                            <tr class="font-semibold">
                                <td colspan="3" class="px-3 py-1.5 text-right">Total alquiler</td>
                                <td class="px-3 py-1.5 text-right tabular-nums">S/ {{ number_format($rental->rental_total, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Documento del cliente (fotos privadas, servidas con permiso) -->
                <div x-data="{ adding: false }" class="rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400">Documento del cliente</p>
                        @can('alquileres.registrar')
                            <flux:button size="sm" variant="subtle" icon="camera" x-on:click="adding = ! adding">
                                Agregar fotos
                            </flux:button>
                        @endcan
                    </div>

                    @if ($rental->documents->isEmpty())
                        <p class="mt-3 text-sm text-amber-600 dark:text-amber-400">Este alquiler no tiene fotos del documento.</p>
                    @else
                        <div class="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-4">
                            @foreach ($rental->documents as $document)
                                <div wire:key="rental-document-{{ $document->id }}" class="group relative overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
                                    <a href="{{ $document->url() }}" target="_blank" rel="noopener" class="block aspect-[4/3] bg-zinc-100 dark:bg-zinc-800">
                                        <img src="{{ $document->url() }}" alt="{{ $document->label ?? 'Documento' }}" loading="lazy" class="size-full object-cover" />
                                    </a>
                                    <p class="truncate px-2 py-1 text-[11px] text-zinc-600 dark:text-zinc-300">{{ $document->label ?? 'Documento' }}</p>
                                    @can('alquileres.editar')
                                        <button type="button" wire:click="deleteDocument({{ $document->id }})"
                                            wire:confirm="¿Eliminar esta foto del documento?"
                                            class="absolute right-1 top-1 hidden size-7 place-items-center rounded-full bg-black/60 text-white group-hover:grid"
                                            aria-label="Eliminar foto">
                                            <flux:icon icon="trash" class="size-3.5" />
                                        </button>
                                    @endcan
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @can('alquileres.registrar')
                        <div x-show="adding" x-collapse class="mt-4 space-y-3">
                            @include('livewire.rental.partials.document-photos')
                            <div class="flex justify-end">
                                <flux:button size="sm" variant="primary" wire:click="saveDocuments" icon="check">
                                    <span wire:loading.remove wire:target="saveDocuments">Guardar fotos</span>
                                    <span wire:loading wire:target="saveDocuments">Guardando...</span>
                                </flux:button>
                            </div>
                        </div>
                    @endcan
                </div>

                @if ($rental->isCancelled())
                    <!-- Alquiler anulado: solo lectura, se puede rehacer con los datos corregidos -->
                    <div class="grid gap-3 text-sm sm:grid-cols-3">
                        <div>
                            <p class="text-xs text-zinc-500">Cliente</p>
                            <p class="font-medium">{{ $rental->client?->display_name }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-zinc-500">Salida</p>
                            <p class="font-medium">{{ $rental->pickup_date?->format('d/m/Y H:i') }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-zinc-500">Devolución prevista</p>
                            <p class="font-medium">{{ $rental->expected_return_date?->format('d/m/Y H:i') }}</p>
                        </div>
                    </div>

                    <div class="flex flex-col-reverse gap-3 border-t border-zinc-100 pt-5 sm:flex-row sm:justify-end dark:border-zinc-800">
                        <flux:modal.close>
                            <flux:button variant="subtle">Cerrar</flux:button>
                        </flux:modal.close>
                        @can('alquileres.registrar')
                            <flux:button :href="route('rental.rentals.create', ['from' => $rental->id])" wire:navigate
                                variant="primary" icon="arrow-path">
                                Rehacer alquiler
                            </flux:button>
                        @endcan
                    </div>
                @elsecannot('alquileres.editar')
                    <!-- Solo lectura: quien no puede editar debe pedir el cambio al administrador -->
                    <div class="grid gap-3 text-sm sm:grid-cols-3">
                        <div>
                            <p class="text-xs text-zinc-500">Cliente</p>
                            <p class="font-medium">{{ $rental->client?->display_name }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-zinc-500">Salida</p>
                            <p class="font-medium">{{ $rental->pickup_date?->format('d/m/Y H:i') }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-zinc-500">Devolución prevista</p>
                            <p class="font-medium">{{ $rental->expected_return_date?->format('d/m/Y H:i') }}</p>
                        </div>
                    </div>
                    <flux:callout icon="information-circle">
                        <flux:callout.text>Si hay un error en este alquiler, solicita la corrección o anulación al administrador.</flux:callout.text>
                    </flux:callout>
                    <div class="flex flex-col-reverse gap-3 border-t border-zinc-100 pt-5 sm:flex-row sm:justify-end dark:border-zinc-800">
                        <flux:modal.close>
                            <flux:button variant="subtle">Cerrar</flux:button>
                        </flux:modal.close>
                        @if ($rental->status === \App\Models\Rental::STATUS_OUT)
                            @can('alquileres.recepcionar')
                                <flux:button :href="route('rental.rentals.return', $rental)" wire:navigate variant="primary" icon="arrow-down-tray">
                                    Recepcionar
                                </flux:button>
                            @endcan
                        @endif
                    </div>
                @else
                    <!-- Datos editables (no afectan inventario) -->
                    <form wire:submit="save" class="flex flex-col gap-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400">Datos del alquiler</p>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <flux:field>
                                    <flux:label>Cliente</flux:label>
                                    <livewire:shared.entity-search-select entityType="client" fieldContext="rental-edit-client"
                                        :selectedId="$client_id" :selectedLabel="$client_name"
                                        :key="'rental-edit-client-' . $rental->id" />
                                    <flux:error name="client_id" />
                                </flux:field>
                            </div>

                            <flux:input wire:model="pickup_date" type="datetime-local" label="Salida" required />
                            <flux:input wire:model="expected_return_date" type="datetime-local" label="Devolución prevista" required />

                            <div class="sm:col-span-2">
                                <flux:textarea wire:model="notes" label="Notas" rows="2" />
                            </div>
                        </div>

                        <!-- Footer -->
                        <div
                            class="flex flex-col-reverse gap-3 border-t border-zinc-100 pt-5 sm:flex-row sm:items-center sm:justify-between dark:border-zinc-800">
                            <div>
                                @if ($rental->isCancellable())
                                    @can('alquileres.anular')
                                        <flux:modal.trigger name="cancel-rental">
                                            <flux:button variant="danger" icon="x-circle">Anular alquiler</flux:button>
                                        </flux:modal.trigger>
                                    @endcan
                                @endif
                            </div>

                            <div class="flex flex-col-reverse gap-3 sm:flex-row">
                                <flux:modal.close>
                                    <flux:button variant="subtle">Cerrar</flux:button>
                                </flux:modal.close>
                                @if ($rental->status === \App\Models\Rental::STATUS_OUT)
                                    @can('alquileres.recepcionar')
                                        <flux:button :href="route('rental.rentals.return', $rental)" wire:navigate icon="arrow-down-tray">
                                            Recepcionar
                                        </flux:button>
                                    @endcan
                                @endif
                                <flux:button type="submit" variant="primary">
                                    <span wire:loading.remove wire:target="save">Guardar cambios</span>
                                    <span wire:loading wire:target="save">Guardando...</span>
                                </flux:button>
                            </div>
                        </div>
                    </form>
                @endif
            </div>
        @endif
    </flux:modal>

    <!-- Confirmación de anulación -->
    <flux:modal name="cancel-rental" class="w-full max-w-md">
        <form wire:submit="cancel" class="flex flex-col gap-4">
            <div>
                <flux:heading size="lg">Anular alquiler {{ $this->rental?->code }}</flux:heading>
                <flux:text class="mt-1">
                    Las prendas que siguen fuera volverán a estar disponibles y, si hubo garantía, se registrará su
                    devolución en caja. El alquiler no se borra: queda como anulado y luego puedes rehacerlo.
                </flux:text>
            </div>

            <flux:textarea wire:model="cancellationReason" label="Motivo de anulación" rows="3"
                placeholder="Ej. Prenda equivocada, se registrará nuevamente." required />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="subtle">Volver</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger">Confirmar anulación</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
