<?php

namespace App\Services\Inventory;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;

/**
 * Reduce las fotos de producto antes de guardarlas en el disco público: las
 * escala (sin agrandar) a un máximo de 800×1000 y las guarda como JPEG
 * comprimido y sin metadatos EXIF.
 */
class ProductImageOptimizer
{
    public const MAX_WIDTH = 800;

    public const MAX_HEIGHT = 1000;

    public const QUALITY = 78;

    /**
     * Optimiza una imagen desde una ruta local y la guarda en el disco público.
     * Devuelve la ruta relativa guardada (para product_media.file_path).
     */
    public function storeFromPath(string $sourcePath, string $directory, string $filename): string
    {
        $path = trim($directory, '/').'/'.pathinfo($filename, PATHINFO_FILENAME).'.jpg';

        Storage::disk('public')->put($path, $this->optimize($sourcePath));

        return $path;
    }

    /**
     * Devuelve el binario JPEG optimizado de la imagen indicada.
     */
    public function optimize(
        string $sourcePath,
        int $maxWidth = self::MAX_WIDTH,
        int $maxHeight = self::MAX_HEIGHT,
        int $quality = self::QUALITY,
    ): string {
        return ImageManager::usingDriver(Driver::class)
            ->decodePath($sourcePath)
            ->orient()
            ->scaleDown($maxWidth, $maxHeight)
            ->encode(new JpegEncoder(quality: $quality, strip: true))
            ->toString();
    }
}
