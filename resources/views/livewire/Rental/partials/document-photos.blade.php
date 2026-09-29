{{-- Captura de fotos del DNI. Requiere el trait ManagesDocumentPhotos en el componente. --}}
@php($required = $required ?? false)

<div class="flex flex-col gap-3">
    <div class="flex items-center justify-between">
        <flux:label>Documento del cliente (DNI){{ $required ? ' *' : '' }}</flux:label>
        <span class="text-[11px] text-zinc-500">{{ count($documentPhotos) }}/6 fotos</span>
    </div>

    <div class="grid grid-cols-2 gap-2">
        {{-- En el celular abre directamente la cámara trasera --}}
        <label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-brand-300 bg-brand-50/60 px-3 py-3 text-sm font-medium text-brand-700 transition hover:bg-brand-50 dark:border-brand-800 dark:bg-brand-950/30 dark:text-brand-300">
            <flux:icon icon="camera" class="size-4" />
            Tomar foto
            <input type="file" accept="image/*" capture="environment" wire:model="photoUploads" multiple class="sr-only" />
        </label>
        <label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-zinc-300 px-3 py-3 text-sm font-medium text-zinc-600 transition hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800/50">
            <flux:icon icon="photo" class="size-4" />
            Subir imagen
            <input type="file" accept="image/*" wire:model="photoUploads" multiple class="sr-only" />
        </label>
    </div>

    <div wire:loading.flex wire:target="photoUploads" class="items-center gap-2 text-xs text-zinc-500">
        <flux:icon.loading class="size-4" /> Cargando foto...
    </div>

    @if (! empty($documentPhotos))
        <div class="grid grid-cols-2 gap-2">
            @foreach ($documentPhotos as $index => $photo)
                <div wire:key="document-photo-{{ $index }}-{{ $photo->getFilename() }}"
                    class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <div class="relative aspect-[4/3] bg-zinc-100 dark:bg-zinc-800">
                        <img src="{{ $photo->temporaryUrl() }}" alt="Foto del documento" class="size-full object-cover" />
                        <button type="button" wire:click="removeDocumentPhoto({{ $index }})"
                            class="absolute right-1.5 top-1.5 grid size-7 place-items-center rounded-full bg-black/60 text-white hover:bg-black/80"
                            aria-label="Quitar foto">
                            <flux:icon icon="x-mark" class="size-4" />
                        </button>
                    </div>
                    <div class="p-1.5">
                        <input type="text" wire:model="documentLabels.{{ $index }}" list="dni-labels" maxlength="60"
                            class="w-full rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs dark:border-zinc-700 dark:bg-zinc-900"
                            placeholder="Anverso, reverso, sello..." />
                    </div>
                </div>
            @endforeach
        </div>

        <datalist id="dni-labels">
            @foreach (\App\Models\RentalMedia::DNI_LABELS as $label)
                <option value="{{ $label }}"></option>
            @endforeach
        </datalist>
    @else
        <p class="text-[11px] text-zinc-500">Fotografía el DNI físico: anverso, reverso y el sello si lo tiene.</p>
    @endif

    <flux:error name="documentPhotos" />
    <flux:error name="photoUploads.*" />
</div>
