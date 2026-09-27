<div class="mx-auto max-w-5xl space-y-6">
    <!-- Encabezado con navegación de retorno -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <flux:button variant="ghost" icon="arrow-left" href="{{ route('inventory.products.index') }}" />
            <div>
                <flux:heading size="xl" class="text-zinc-900 dark:text-white">
                    {{ $isEditing ? 'Editar Producto: ' . $name : 'Nuevo Producto' }}
                </flux:heading>
                <flux:subheading class="text-zinc-500 dark:text-zinc-400">
                    Defina los detalles generales y especificaciones del modelo base
                </flux:subheading>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <flux:button variant="filled" href="{{ route('inventory.products.index') }}">
                Cancelar
            </flux:button>
            <flux:button variant="primary" wire:click="save" icon="check">
                {{ $isEditing ? 'Guardar Cambios' : 'Registrar Producto' }}
            </flux:button>
        </div>
    </div>

    @if ($hasPhysicalItems)
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/50 dark:bg-amber-950/20">
            <div class="flex items-center gap-3 text-amber-800 dark:text-amber-400">
                <flux:icon icon="exclamation-triangle" class="h-5 w-5 flex-shrink-0" />
                <p class="text-sm">
                    <strong>Aviso:</strong> Este producto ya cuenta con unidades físicas registradas. La Categoría, Marca y Género han sido protegidos para mantener la integridad del inventario.
                </p>
            </div>
        </div>
    @endif

    <form wire:submit="save" class="space-y-6">
        <!-- Bloque 1: Clasificación General -->
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <h3 class="mb-4 text-base font-semibold text-zinc-900 dark:text-white">Información Principal</h3>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <!-- Código SKU -->
                <div>
                    <flux:input label="Código / SKU Base" wire:model="code" placeholder="PROD-001" required />
                </div>

                <!-- Nombre -->
                <div class="md:col-span-2">
                    <flux:input label="Nombre del Producto" wire:model="name" placeholder="Ej: Terno Slim Fit Azul Noche" required />
                </div>

                <!-- Categoría -->
                <div>
                    <flux:select label="Categoría" wire:model="category_id" :disabled="$hasPhysicalItems" required>
                        <flux:select.option value="">Seleccione una categoría</flux:select.option>
                        @foreach ($categoryOptions as $category)
                            <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <!-- Marca -->
                <div>
                    <flux:select label="Marca" wire:model="brand_id" :disabled="$hasPhysicalItems">
                        <flux:select.option value="">Sin marca</flux:select.option>
                        @foreach ($brandOptions as $brand)
                            <flux:select.option value="{{ $brand->id }}">{{ $brand->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <!-- Género -->
                <div>
                    <flux:select label="Género" wire:model="gender" :disabled="$hasPhysicalItems" required>
                        <flux:select.option value="masculino">Masculino</flux:select.option>
                        <flux:select.option value="femenino">Femenino</flux:select.option>
                        <flux:select.option value="unisex">Unisex</flux:select.option>
                        <flux:select.option value="nino">Niño/a</flux:select.option>
                    </flux:select>
                </div>

                <!-- Precio Referencial -->
                <div>
                    <flux:input label="Precio Base Referencial (S/)" wire:model="price" type="number" step="0.50" min="0" required />
                </div>

                <!-- Descripción -->
                <div class="md:col-span-3">
                    <flux:textarea label="Descripción general o notas de confección" wire:model="description" rows="3" />
                </div>
            </div>
        </div>

        <!-- Bloque 2: Permisos y Estado -->
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <h3 class="mb-4 text-base font-semibold text-zinc-900 dark:text-white">Operatividad del Modelo</h3>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                <flux:switch wire:model="allow_rental" label="Permitir para Alquiler" description="Habilita la prenda para reservas y alquileres." />
                <flux:switch wire:model="allow_sale" label="Permitir para Venta" description="Habilita la prenda para venta directa en POS." />
                <flux:switch wire:model="is_active" label="Estado Activo" description="Los productos inactivos no aparecerán en búsquedas." />
            </div>
        </div>

        <!-- Bloque 3: Especificaciones de Ficha Técnica (Atributos) -->
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <h3 class="text-base font-semibold text-zinc-900 dark:text-white">Ficha Técnica & Atributos</h3>
            <p class="mb-4 text-xs text-zinc-500 dark:text-zinc-400">Seleccione las características técnicas aplicables a este modelo (No generan stock).</p>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($attributes as $attribute)
                    <div class="space-y-2 rounded-lg border border-zinc-100 p-3 dark:border-zinc-800">
                        <label class="text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300">
                            {{ $attribute->name }}
                        </label>

                        <div class="space-y-1.5 max-h-40 overflow-y-auto pr-1">
                            @foreach ($attribute->values as $value)
                                <label class="flex items-center gap-2 text-sm text-zinc-700 cursor-pointer dark:text-zinc-300">
                                    <input type="checkbox" 
                                           wire:model="selectedAttributeValueIds" 
                                           value="{{ $value->id }}" 
                                           class="rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:checked:bg-white" />
                                    <span>{{ $value->value }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-4">
            <flux:button variant="filled" href="{{ route('inventory.products.index') }}">
                Cancelar
            </flux:button>
            <flux:button variant="primary" type="submit" icon="check">
                {{ $isEditing ? 'Guardar Cambios' : 'Registrar Producto' }}
            </flux:button>
        </div>
    </form>
</div>