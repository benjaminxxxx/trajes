<flux:modal wire:model="show" class="w-full max-w-xl">
    <form wire:submit="save" class="flex flex-col gap-4">
        <div>
            <h2 class="text-xl font-semibold text-zinc-900 dark:text-white">
                {{ $productId ? 'Editar producto' : 'Nuevo producto' }}
            </h2>
            <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                Solo lo esencial. Si la categoría o la marca no existen, se crean al guardar.
            </p>
        </div>

        @if ($hasPhysicalItems)
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.text>
                    Este producto ya tiene prendas registradas: la categoría, la marca y el género no se pueden cambiar.
                </flux:callout.text>
            </flux:callout>
        @endif

        <flux:input wire:model="name" label="Nombre" placeholder="Ej: Terno Slim Fit Azul Noche" required />

        <div class="grid grid-cols-2 gap-3">
            <flux:input wire:model="category" label="Categoría" list="product-modal-categories" autocomplete="off"
                placeholder="Elegir o escribir nueva" :disabled="$hasPhysicalItems" required />
            <flux:input wire:model="brand" label="Marca" list="product-modal-brands" autocomplete="off"
                placeholder="Sin marca" :disabled="$hasPhysicalItems" />

            <flux:select wire:model="gender" label="Género" :disabled="$hasPhysicalItems">
                <flux:select.option value="unisex">Unisex</flux:select.option>
                <flux:select.option value="masculino">Masculino</flux:select.option>
                <flux:select.option value="femenino">Femenino</flux:select.option>
                <flux:select.option value="nino">Niño/a</flux:select.option>
            </flux:select>
            <flux:input wire:model="price" type="number" step="0.01" min="0" inputmode="decimal" label="Precio base (S/)" required />
        </div>

        <datalist id="product-modal-categories">
            @foreach ($categoryOptions as $option)
                <option value="{{ $option }}"></option>
            @endforeach
        </datalist>
        <datalist id="product-modal-brands">
            @foreach ($brandOptions as $option)
                <option value="{{ $option }}"></option>
            @endforeach
        </datalist>

        {{-- Operatividad: un solo control en vez de dos interruptores --}}
        <flux:field>
            <flux:label>Se usa para</flux:label>
            <div class="grid grid-cols-3 gap-1 rounded-xl bg-zinc-100 p-1 dark:bg-zinc-800">
                @foreach (['both' => 'Alquiler y venta', 'rental' => 'Solo alquiler', 'sale' => 'Solo venta'] as $value => $label)
                    <button type="button" x-on:click="$wire.usage = '{{ $value }}'"
                        class="rounded-lg px-2 py-1.5 text-xs font-semibold transition-colors"
                        :class="$wire.usage === '{{ $value }}'
                            ? 'bg-white text-violet-700 shadow-sm dark:bg-zinc-950 dark:text-violet-300'
                            : 'text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200'">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
            <flux:error name="usage" />
        </flux:field>

        {{-- Fotos: se guardan (ya reducidas) junto con el producto --}}
        <div class="flex flex-col gap-2">
            <div class="flex items-center justify-between">
                <flux:label>{{ $productId ? 'Agregar fotos' : 'Fotos' }}</flux:label>
                <span class="text-[11px] text-zinc-500">{{ count($photos) }}/{{ $maxPhotos }}</span>
            </div>

            <div class="grid grid-cols-4 gap-2">
                @foreach ($photos as $index => $photo)
                    <div wire:key="product-photo-{{ $index }}-{{ $photo->getFilename() }}"
                        class="relative aspect-square overflow-hidden rounded-xl border border-zinc-200 bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800">
                        <img src="{{ $photo->temporaryUrl() }}" alt="Foto del producto" class="size-full object-cover" />
                        @if ($index === 0 && ! $productId)
                            <span class="absolute bottom-1 left-1 rounded bg-emerald-600 px-1 text-[9px] font-bold text-white">Portada</span>
                        @endif
                        <button type="button" wire:click="removePhoto({{ $index }})"
                            class="absolute right-1 top-1 grid size-6 place-items-center rounded-full bg-black/60 text-white hover:bg-black/80"
                            aria-label="Quitar foto">
                            <flux:icon icon="x-mark" class="size-3.5" />
                        </button>
                    </div>
                @endforeach

                @if (count($photos) < $maxPhotos)
                    {{-- En el celular abre directamente la cámara trasera --}}
                    <label class="flex aspect-square cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border border-dashed border-violet-300 bg-violet-50/60 text-[11px] font-medium text-violet-700 transition hover:bg-violet-50 dark:border-violet-800 dark:bg-violet-950/30 dark:text-violet-300">
                        <flux:icon icon="camera" class="size-5" />
                        Tomar foto
                        <input type="file" accept="image/*" capture="environment" wire:model="photoUploads" class="sr-only" />
                    </label>
                    <label class="flex aspect-square cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border border-dashed border-zinc-300 text-[11px] font-medium text-zinc-600 transition hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800/50">
                        <flux:icon icon="photo" class="size-5" />
                        Galería
                        <input type="file" accept="image/*" wire:model="photoUploads" multiple class="sr-only" />
                    </label>
                @endif
            </div>

            <div wire:loading.flex wire:target="photoUploads" class="items-center gap-2 text-xs text-zinc-500">
                <flux:icon.loading class="size-4" /> Cargando foto...
            </div>

            <flux:error name="photos" />
            <flux:error name="photoUploads.*" />
        </div>

        {{-- Más opciones --}}
        <button type="button" wire:click="$toggle('moreOptions')"
            class="flex items-center justify-between rounded-xl border border-dashed border-zinc-200 px-3 py-2 text-left dark:border-zinc-700">
            <span>
                <span class="block text-sm font-semibold text-zinc-800 dark:text-zinc-200">Más opciones</span>
                <span class="block text-[11px] text-zinc-500">Código SKU, descripción, estado y ficha técnica</span>
            </span>
            <flux:icon :icon="$moreOptions ? 'chevron-up' : 'chevron-down'" class="size-4 text-violet-600 dark:text-violet-400" />
        </button>
        {{-- El SKU es obligatorio: si falla, se ve aunque la sección esté cerrada --}}
        @unless ($moreOptions)
            <flux:error name="code" />
        @endunless

        @if ($moreOptions)
            <div class="flex flex-col gap-3">
                <flux:input wire:model="code" label="Código / SKU base" placeholder="PROD-001" required />
                <flux:textarea wire:model="description" label="Descripción general o notas de confección" rows="2" />
                <flux:switch wire:model="is_active" label="Producto activo" description="Los inactivos no aparecen en las búsquedas." align="left" />

                @if ($technicalAttributes->isNotEmpty())
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-zinc-400">Ficha técnica</p>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach ($technicalAttributes as $attribute)
                                <div wire:key="product-attribute-{{ $attribute->id }}" class="rounded-lg border border-zinc-100 p-2 dark:border-zinc-800">
                                    <p class="mb-1 text-[11px] font-bold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">{{ $attribute->name }}</p>
                                    <div class="max-h-28 space-y-1 overflow-y-auto pr-1">
                                        @foreach ($attribute->values as $value)
                                            <label wire:key="product-attribute-value-{{ $value->id }}" class="flex cursor-pointer items-center gap-2 text-xs text-zinc-700 dark:text-zinc-300">
                                                <input type="checkbox" wire:model="selectedAttributeValueIds" value="{{ $value->id }}"
                                                    class="rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900 dark:border-zinc-700 dark:bg-zinc-800" />
                                                <span>{{ $value->value }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <div class="flex flex-col-reverse gap-2 border-t border-zinc-100 pt-4 sm:flex-row sm:justify-end dark:border-zinc-800">
            <flux:modal.close>
                <flux:button variant="subtle">Cancelar</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled" wire:target="save, photoUploads">
                <span wire:loading.remove wire:target="save">{{ $productId ? 'Guardar cambios' : 'Guardar producto' }}</span>
                <span wire:loading wire:target="save">Guardando...</span>
            </flux:button>
        </div>
    </form>
</flux:modal>
