<flux:modal wire:model="show" class="md:max-w-3xl">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg" class="text-zinc-900 dark:text-white">
                Galería de Medios: {{ $product?->name }}
            </flux:heading>
            <flux:subheading class="text-zinc-500 dark:text-zinc-400">
                Gestione las imágenes y videos promocionales del producto por color
            </flux:subheading>
        </div>

        <!-- Zona de Carga -->
        <form wire:submit="uploadMedia" class="rounded-xl border border-dashed border-zinc-300 p-4 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-900/50 space-y-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <flux:input label="Seleccionar Fotos/Videos" type="file" wire:model="newFiles" multiple />
                </div>

                <div>
                    <flux:select label="Asociar a Color (Opcional)" wire:model="colorId">
                        <flux:select.option value="">Aplica a todos los colores</flux:select.option>
                        @foreach ($colors as $color)
                            <flux:select.option value="{{ $color->id }}">{{ $color->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </div>

            <div class="flex items-center justify-between pt-2">
                <flux:switch wire:model="isPrimary" label="Establecer primera foto como Portada" />

                <flux:button type="submit" variant="primary" icon="arrow-up-tray" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="newFiles, uploadMedia">Subir Archivos</span>
                    <span wire:loading wire:target="newFiles, uploadMedia">Subiendo...</span>
                </flux:button>
            </div>
        </form>

        <!-- Grilla de Medios Actuales -->
        <div class="space-y-2">
            <h4 class="text-sm font-semibold text-zinc-900 dark:text-white">Archivos guardados</h4>

            @if ($product && $product->media->count() > 0)
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4">
                    @foreach ($product->media as $item)
                        <div wire:key="media-item-{{ $item->id }}" 
                             class="group relative overflow-hidden rounded-lg border border-zinc-200 bg-zinc-100 dark:border-zinc-800 dark:bg-zinc-900">
                            
                            @if ($item->file_type === 'video')
                                <video src="{{ Storage::url($item->file_path) }}" class="h-32 w-full object-cover"></video>
                            @else
                                <img src="{{ Storage::url($item->file_path) }}" class="h-32 w-full object-cover" />
                            @endif

                            <!-- Badge de Estado Portada / Color -->
                            <div class="absolute top-2 left-2 flex flex-col gap-1">
                                @if ($item->is_primary)
                                    <span class="rounded bg-emerald-600 px-1.5 py-0.5 text-[10px] font-bold text-white shadow">
                                        Portada
                                    </span>
                                @endif
                                @if ($item->color)
                                    <span class="rounded bg-zinc-900/80 px-1.5 py-0.5 text-[10px] font-medium text-white backdrop-blur">
                                        {{ $item->color->name }}
                                    </span>
                                @endif
                            </div>

                            <!-- Menú Flotante de Acciones -->
                            <div class="absolute inset-0 flex items-center justify-center gap-2 bg-zinc-900/60 opacity-0 transition-opacity group-hover:opacity-100">
                                @if (!$item->is_primary)
                                    <flux:button size="sm" variant="filled" icon="star" title="Marcar como Portada" 
                                        wire:click="setPrimary({{ $item->id }})" />
                                @endif

                                <flux:button size="sm" variant="danger" icon="trash" title="Eliminar"
                                    wire:click="deleteMedia({{ $item->id }})"
                                    wire:confirm="¿Desea eliminar esta imagen de la galería?" />
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="rounded-lg border border-zinc-200 py-8 text-center text-xs text-zinc-400 dark:border-zinc-800">
                    Este producto no tiene fotos o videos subidos aún.
                </div>
            @endif
        </div>
    </div>
</flux:modal>