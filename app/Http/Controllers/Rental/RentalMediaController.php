<?php

namespace App\Http\Controllers\Rental;

use App\Http\Controllers\Controller;
use App\Models\RentalMedia;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sirve una foto de documento de un alquiler desde el disco privado. La ruta
 * exige el permiso `alquileres.ver`; nunca hay una URL pública al archivo.
 */
class RentalMediaController extends Controller
{
    public function __invoke(RentalMedia $media): StreamedResponse
    {
        abort_unless(Storage::disk(RentalMedia::DISK)->exists($media->file_path), 404);

        return Storage::disk(RentalMedia::DISK)->response($media->file_path, null, [
            // Datos personales: que no queden en cachés compartidas.
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}
