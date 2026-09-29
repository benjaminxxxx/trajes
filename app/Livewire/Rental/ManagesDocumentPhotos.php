<?php

namespace App\Livewire\Rental;

use App\Models\RentalMedia;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Carga de fotos del documento del cliente (DNI) antes de guardarlas.
 *
 * Cada vez que se toma o elige una foto llega a `$photoUploads` y se acumula
 * en `$documentPhotos`, así se pueden tomar varias con la cámara una tras
 * otra. Cada foto lleva su etiqueta (Anverso, Reverso, Sello...).
 */
trait ManagesDocumentPhotos
{
    use WithFileUploads;

    /** @var array<int, TemporaryUploadedFile> Fotos recién elegidas (se vacía al acumularlas). */
    public array $photoUploads = [];

    /** @var array<int, TemporaryUploadedFile> */
    public array $documentPhotos = [];

    /** @var array<int, string> */
    public array $documentLabels = [];

    public function updatedPhotoUploads(): void
    {
        $this->validate([
            'photoUploads.*' => ['image', 'max:12288'],
        ], [
            'photoUploads.*.image' => 'Solo se aceptan fotos (JPG, PNG, WEBP).',
            'photoUploads.*.max' => 'Cada foto puede pesar hasta 12 MB.',
        ]);

        foreach ($this->photoUploads as $photo) {
            $index = count($this->documentPhotos);
            $this->documentPhotos[] = $photo;
            $this->documentLabels[] = RentalMedia::DNI_LABELS[min($index, count(RentalMedia::DNI_LABELS) - 1)];
        }

        $this->photoUploads = [];
    }

    public function removeDocumentPhoto(int $index): void
    {
        unset($this->documentPhotos[$index], $this->documentLabels[$index]);

        $this->documentPhotos = array_values($this->documentPhotos);
        $this->documentLabels = array_values($this->documentLabels);
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function documentPhotoRules(bool $required): array
    {
        return [
            'documentPhotos' => $required ? ['required', 'array', 'min:1', 'max:6'] : ['array', 'max:6'],
            'documentPhotos.*' => ['image', 'max:12288'],
            'documentLabels.*' => ['nullable', 'string', 'max:60'],
        ];
    }

    /**
     * @return array<int, array{file: TemporaryUploadedFile, label: ?string}>
     */
    protected function pendingDocumentPhotos(): array
    {
        return array_map(
            fn (TemporaryUploadedFile $file, int $index) => ['file' => $file, 'label' => $this->documentLabels[$index] ?? null],
            $this->documentPhotos,
            array_keys($this->documentPhotos),
        );
    }

    protected function resetDocumentPhotos(): void
    {
        $this->reset(['photoUploads', 'documentPhotos', 'documentLabels']);
    }
}
