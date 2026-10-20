<flux:modal wire:model="show" class="w-full max-w-xl">
    @if ($step === 'category')
        {{-- Paso 1: qué se va a registrar (y si ya existe) --}}
        <div class="flex flex-col gap-4">
            <div>
                <h2 class="text-xl font-semibold text-zinc-900 dark:text-white">¿Qué vas a registrar?</h2>
                <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                    Elige el tipo de prenda. Si ya lo tienes registrado, solo compra más en vez de crearlo de nuevo.
                </p>
            </div>

            <flux:input wire:model.live.debounce.300ms="finder" icon="magnifying-glass" autofocus
                placeholder="¿Ya lo tienes? Busca por nombre, código o tela..." />

            @if (mb_strlen(trim($finder)) >= 2)
                <div class="flex flex-col gap-2">
                    @forelse ($finderResults as $product)
                        @include('livewire.inventory.partials.product-choice', ['product' => $product, 'context' => $context, 'showCategory' => true])
                    @empty
                        <p class="rounded-xl bg-zinc-50 px-3 py-2 text-xs text-zinc-500 dark:bg-zinc-800/60">
                            No hay productos con «{{ trim($finder) }}». Elige su categoría abajo para registrarlo.
                        </p>
                    @endforelse
                </div>
            @endif

            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                @foreach ($chooserCategories as $chooserCategory)
                    <button type="button" wire:key="chooser-category-{{ $chooserCategory->id }}"
                        wire:click="chooseCategory({{ $chooserCategory->id }})"
                        class="flex flex-col items-start gap-1 rounded-xl border border-zinc-200 p-3 text-left transition hover:border-violet-400 hover:bg-violet-50/60 dark:border-zinc-700 dark:hover:border-violet-700 dark:hover:bg-violet-950/30">
                        <flux:icon icon="tag" class="size-4 text-violet-600 dark:text-violet-400" />
                        <span class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $chooserCategory->name }}</span>
                        <span class="text-[11px] text-zinc-500">
                            {{ $chooserCategory->products_count === 0 ? 'Sin productos aún' : trans_choice(':count registrado|:count registrados', $chooserCategory->products_count) }}
                        </span>
                    </button>
                @endforeach

                <button type="button" wire:click="startNew"
                    class="flex flex-col items-start gap-1 rounded-xl border border-dashed border-zinc-300 p-3 text-left transition hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800/50">
                    <flux:icon icon="plus" class="size-4 text-zinc-500" />
                    <span class="text-sm font-semibold text-zinc-700 dark:text-zinc-200">Otra categoría</span>
                    <span class="text-[11px] text-zinc-500">La escribes en el formulario</span>
                </button>
            </div>
        </div>
    @elseif ($step === 'products')
        {{-- Paso 2: lo que ya existe en la categoría --}}
        <div class="flex flex-col gap-4">
            <div class="flex items-start gap-2">
                <flux:button size="sm" variant="ghost" icon="arrow-left" wire:click="backToCategories" aria-label="Volver" />
                <div>
                    <h2 class="text-xl font-semibold text-zinc-900 dark:text-white">{{ $chosenCategory?->name }}</h2>
                    <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                        Ya tienes {{ trans_choice(':count producto|:count productos', $existingProducts->count()) }}.
                        Si lo que compraste es uno de estos, {{ $context === 'purchase' ? 'agrégalo a la compra' : 'compra más' }}:
                        las tallas y colores nuevos se eligen en la compra.
                    </p>
                </div>
            </div>

            <div class="flex max-h-[50vh] flex-col gap-2 overflow-y-auto pr-1">
                @foreach ($existingProducts as $product)
                    <div wire:key="existing-product-{{ $product->id }}">
                        @include('livewire.inventory.partials.product-choice', ['product' => $product, 'context' => $context])
                    </div>
                @endforeach
            </div>

            <flux:button variant="filled" icon="plus" wire:click="startNew({{ $chosenCategoryId }})" class="w-full">
                Es otro {{ Str::lower($chosenCategory?->name ?? 'producto') }}: registrarlo nuevo
            </flux:button>
        </div>
    @else
    <form wire:submit="save" class="flex flex-col gap-4">
        <div class="flex items-start gap-2">
            @if (! $productId && $categoryOptions->isNotEmpty())
                <flux:button size="sm" variant="ghost" icon="arrow-left" wire:click="backToCategories" aria-label="Volver" />
            @endif
            <div>
                <h2 class="text-xl font-semibold text-zinc-900 dark:text-white">
                    {{ $productId ? 'Editar producto' : 'Nuevo producto' }}
                </h2>
                <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                    Solo lo esencial. Si la categoría o la marca no existen, se crean al guardar.
                </p>
            </div>
        </div>

        @if ($hasPhysicalItems)
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.text>
                    Este producto ya tiene prendas registradas: la categoría, la marca y el género no se pueden cambiar.
                </flux:callout.text>
            </flux:callout>
        @endif

        <flux:input wire:model.live.blur="name" label="Nombre" placeholder="Ej: Terno Slim Fit Azul Noche" required />

        @if ($similarProducts->isNotEmpty())
            <div class="flex flex-col gap-2 rounded-xl border border-amber-200 bg-amber-50/70 p-3 dark:border-amber-900/50 dark:bg-amber-950/20">
                <p class="flex items-center gap-1.5 text-xs font-semibold text-amber-700 dark:text-amber-400">
                    <flux:icon icon="exclamation-triangle" class="size-4" />
                    ¿Es alguno de estos? Ya están registrados.
                </p>
                @foreach ($similarProducts as $product)
                    <div wire:key="similar-product-{{ $product->id }}">
                        @include('livewire.inventory.partials.product-choice', ['product' => $product, 'context' => $context])
                    </div>
                @endforeach
            </div>
        @endif

        <div class="grid grid-cols-2 gap-3">
            <flux:input wire:model.live.blur="category" label="Categoría" list="product-modal-categories" autocomplete="off"
                placeholder="Elegir o escribir nueva" :disabled="$hasPhysicalItems" required />
            <flux:input wire:model="brand" label="Marca" list="product-modal-brands" autocomplete="off"
                placeholder="Sin marca" :disabled="$hasPhysicalItems" />

            <flux:select wire:model="gender" label="Género" :disabled="$hasPhysicalItems">
                <flux:select.option value="unisex">Unisex</flux:select.option>
                <flux:select.option value="masculino">Masculino</flux:select.option>
                <flux:select.option value="femenino">Femenino</flux:select.option>
                <flux:select.option value="nino">Niño/a</flux:select.option>
            </flux:select>
            <div></div>

            <flux:input wire:model="purchase_price" type="number" step="0.01" min="0" inputmode="decimal"
                label="Precio de compra (S/)" description="Lo que pagas al proveedor; se propone al registrar una compra." />
            <flux:input wire:model="price" type="number" step="0.01" min="0" inputmode="decimal"
                label="Precio de venta (S/)" description="Precio sugerido al vender (con IGV)." required />
        </div>

        {{-- Características: texto libre con sugerencias (no se atan al catálogo) --}}
        <div class="flex flex-col gap-2">
            <div class="flex items-center justify-between">
                <flux:label>Características</flux:label>
                <span class="text-[11px] text-zinc-500">Elige una sugerencia o escribe una nueva</span>
            </div>

            @if ($characteristics !== [])
                <div class="grid grid-cols-2 gap-2">
                    @foreach ($characteristics as $index => $row)
                        <div wire:key="characteristic-{{ $index }}-{{ $row['attribute_id'] ?? Str::slug($row['name']) }}" class="relative">
                            <flux:input size="sm" wire:model="characteristics.{{ $index }}.value" :label="$row['name']"
                                list="characteristic-suggestions-{{ $index }}" autocomplete="off" placeholder="Ej: {{ $suggestions[$row['attribute_id']][0] ?? '—' }}" />
                            <button type="button" wire:click="removeCharacteristic({{ $index }})"
                                class="absolute right-0 top-0 text-zinc-400 hover:text-red-500" aria-label="Quitar {{ $row['name'] }}">
                                <flux:icon icon="x-mark" class="size-3.5" />
                            </button>
                            <datalist id="characteristic-suggestions-{{ $index }}">
                                @foreach ($suggestions[$row['attribute_id']] ?? [] as $suggestion)
                                    <option value="{{ $suggestion }}"></option>
                                @endforeach
                            </datalist>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="flex items-center gap-2">
                <flux:input size="sm" wire:model="newCharacteristic" wire:keydown.enter.prevent="addCharacteristic"
                    list="characteristic-names" autocomplete="off" placeholder="Agregar característica: Tela, Corte, Cuero..." class="flex-1" />
                <flux:button size="sm" icon="plus" wire:click="addCharacteristic" aria-label="Agregar característica" />
            </div>
            <datalist id="characteristic-names">
                @foreach ($characteristicNames as $characteristicName)
                    <option value="{{ $characteristicName }}"></option>
                @endforeach
            </datalist>
            <flux:error name="characteristics.*.value" />
        </div>

        {{-- Etiquetas: las automáticas salen solas; a mano solo lo que no está en otro lado --}}
        <div class="flex flex-col gap-2">
            <div class="flex items-center justify-between">
                <flux:label>Etiquetas para buscar</flux:label>
                <span class="text-[11px] text-zinc-500">Ej: Quinceañera, Señora, Boda</span>
            </div>

            <div class="flex flex-wrap gap-1.5">
                @foreach ($automaticTags as $automaticTag)
                    <span class="rounded-full bg-zinc-100 px-2.5 py-0.5 text-xs text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300"
                        title="Automática: sale de la categoría, género, marca, características, tallas o colores">{{ $automaticTag }}</span>
                @endforeach
                @foreach ($tags as $index => $tag)
                    <span wire:key="product-tag-{{ $index }}-{{ Str::slug($tag) }}"
                        class="inline-flex items-center gap-1 rounded-full bg-violet-100 py-0.5 pl-2.5 pr-1 text-xs font-medium text-violet-700 dark:bg-violet-950/50 dark:text-violet-300">
                        {{ $tag }}
                        <button type="button" wire:click="removeTag({{ $index }})" class="grid size-4 place-items-center rounded-full hover:bg-violet-200 dark:hover:bg-violet-900"
                            aria-label="Quitar {{ $tag }}">
                            <flux:icon icon="x-mark" class="size-3" />
                        </button>
                    </span>
                @endforeach
            </div>

            <div class="flex items-center gap-2">
                <flux:input size="sm" wire:model="newTag" wire:keydown.enter.prevent="addTag" list="product-tag-suggestions"
                    autocomplete="off" placeholder="Agregar etiqueta: para quién o para qué ocasión" class="flex-1" />
                <flux:button size="sm" icon="plus" wire:click="addTag" aria-label="Agregar etiqueta" />
            </div>
            <datalist id="product-tag-suggestions">
                @foreach ($tagSuggestions as $suggestion)
                    <option value="{{ $suggestion }}"></option>
                @endforeach
            </datalist>
            <p class="text-[11px] text-zinc-500">
                Las grises se agregan solas y se actualizan al comprar nuevas tallas o colores.
            </p>
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
                <span class="block text-[11px] text-zinc-500">Código SKU, descripción y estado</span>
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
    @endif
</flux:modal>
