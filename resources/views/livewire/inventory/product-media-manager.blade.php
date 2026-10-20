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
                {{-- Revisa el peso en el navegador: un video de 200 MB no debe subir entero para recién ser rechazado. --}}
                <div x-data="{
                        progress: null,
                        tooBig: [],
                        check(event) {
                            const limits = { photo: {{ $maxPhotoMb }}, video: {{ $maxVideoMb }} };
                            this.tooBig = [...event.target.files]
                                .filter(file => file.size > (file.type.startsWith('video/') ? limits.video : limits.photo) * 1024 * 1024)
                                .map(file => `«${file.name}» pesa ${(file.size / 1024 / 1024).toFixed(1)} MB`);

                            if (this.tooBig.length) {
                                event.target.value = '';
                                event.stopImmediatePropagation();
                            }
                        },
                    }"
                    x-on:livewire-upload-start="progress = 0"
                    x-on:livewire-upload-progress="progress = $event.detail.progress"
                    x-on:livewire-upload-finish="progress = null"
                    x-on:livewire-upload-error="progress = null"
                    x-on:livewire-upload-cancel="progress = null">
                    <flux:field>
                        <flux:label>Seleccionar fotos o videos</flux:label>
                        <input type="file" wire:model="newFiles" multiple accept="image/jpeg,image/png,image/webp,video/mp4,video/quicktime"
                            x-on:change.capture="check($event)"
                            class="block w-full text-sm text-zinc-600 file:mr-3 file:rounded-lg file:border-0 file:bg-violet-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-violet-700 hover:file:bg-violet-100 dark:text-zinc-300 dark:file:bg-violet-950/50 dark:file:text-violet-300" />
                        <flux:description>Fotos hasta {{ $maxPhotoMb }} MB · videos hasta {{ $maxVideoMb }} MB.</flux:description>
                    </flux:field>

                    <template x-if="tooBig.length">
                        <div class="mt-2 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700 dark:bg-red-950/40 dark:text-red-300">
                            <p class="font-semibold">No se subió nada: estos archivos superan el límite.</p>
                            <template x-for="line in tooBig"><p x-text="line"></p></template>
                            <p class="mt-1">Compártelo desde el celular en calidad 720p o comprímelo y vuelve a intentarlo.</p>
                        </div>
                    </template>

                    <div x-show="progress !== null" x-cloak class="mt-2">
                        <div class="h-1.5 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                            <div class="h-full bg-violet-600 transition-all" x-bind:style="`width: ${progress}%`"></div>
                        </div>
                        <p class="mt-1 text-[11px] text-zinc-500">Subiendo <span x-text="progress"></span>%… no cierres esta ventana.</p>
                    </div>

                    <flux:error name="newFiles" />
                    @foreach ($errors->get('newFiles.*') as $messages)
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $messages[0] }}</p>
                    @endforeach
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