<?php

namespace App\Services\Rental;

use App\Models\Rental;
use App\Models\RentalMedia;
use App\Services\Inventory\ProductImageOptimizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Fotos de los documentos de un alquiler (DNI: anverso, reverso, sello...).
 *
 * Se guardan en el disco privado, reducidas para que el texto siga legible
 * sin ocupar varios MB por foto (una foto de celular suele pesar 3-5 MB).
 */
class RentalDocumentService
{
    public const MAX_SIDE = 1600;

    public const QUALITY = 82;

    public function __construct(protected ProductImageOptimizer $optimizer) {}

    /**
     * @param  array<int, array{file: UploadedFile, label?: ?string}>  $photos
     * @return array<int, RentalMedia>
     */
    public function attach(Rental $rental, array $photos, string $type = 'dni'): array
    {
        $stored = [];

        try {
            foreach ($photos as $photo) {
                $path = sprintf('rentals/%d/%s-%s.jpg', $rental->id, $type, Str::lower(Str::random(12)));

                Storage::disk(RentalMedia::DISK)->put(
                    $path,
                    $this->optimizer->optimize($photo['file']->getRealPath(), self::MAX_SIDE, self::MAX_SIDE, self::QUALITY),
                );

                $stored[] = RentalMedia::create([
                    'rental_id' => $rental->id,
                    'user_id' => Auth::id(),
                    'file_path' => $path,
                    'type' => $type,
                    'label' => filled($photo['label'] ?? null) ? trim($photo['label']) : null,
                ]);
            }
        } catch (\Throwable $e) {
            // Si una foto falla, no quedan fotos a medias del mismo envío.
            foreach ($stored as $media) {
                $this->delete($media);
            }

            throw $e;
        }

        return $stored;
    }

    /**
     * Copia las fotos del documento de un alquiler a otro (al rehacer uno
     * anulado con el mismo cliente). Se copian los archivos para que borrar
     * una foto en un alquiler no afecte al otro.
     */
    public function copy(Rental $from, Rental $to, string $type = 'dni'): void
    {
        $disk = Storage::disk(RentalMedia::DISK);

        foreach ($from->media()->where('type', $type)->get() as $media) {
            if (! $disk->exists($media->file_path)) {
                continue;
            }

            $path = sprintf('rentals/%d/%s-%s.jpg', $to->id, $type, Str::lower(Str::random(12)));
            $disk->copy($media->file_path, $path);

            RentalMedia::create([
                'rental_id' => $to->id,
                'user_id' => Auth::id(),
                'file_path' => $path,
                'type' => $type,
                'label' => $media->label,
            ]);
        }
    }

    public function delete(RentalMedia $media): void
    {
        Storage::disk(RentalMedia::DISK)->delete($media->file_path);
        $media->delete();
    }
}
