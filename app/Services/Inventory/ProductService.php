<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\ProductMedia;
use DB;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Str;

class ProductService
{
    /**
     * Guardar medio (imagen o video) en la galería del producto.
     */
    public function agregarMedia(Product $product, UploadedFile $file, ?int $colorId = null, bool $isPrimary = false): ProductMedia
    {
        return DB::transaction(function () use ($product, $file, $colorId, $isPrimary) {
            $path = $file->store('products/' . $product->id, 'public');
            $fileType = str_starts_with($file->getMimeType(), 'video/') ? 'video' : 'image';

            if ($isPrimary) {
                $product->media()->update(['is_primary' => false]);
            }

            // Si es la primera foto del producto, se asigna como principal automáticamente
            if ($product->media()->count() === 0) {
                $isPrimary = true;
            }

            $maxOrder = (int) $product->media()->max('sort_order');

            return $product->media()->create([
                'color_id' => $colorId,
                'file_path' => $path,
                'file_type' => $fileType,
                'sort_order' => $maxOrder + 1,
                'is_primary' => $isPrimary,
            ]);
        });
    }
    /**
     * Marcar una foto/video específico como portada principal.
     */
    public function marcarComoPrincipal(Product $product, int $mediaId): void
    {
        DB::transaction(function () use ($product, $mediaId) {
            $product->media()->update(['is_primary' => false]);
            $product->media()->where('id', $mediaId)->update(['is_primary' => true]);
        });
    }
    /**
     * Eliminar medio del storage y de la base de datos.
     */
    public function eliminarMedia(Product $product, int $mediaId): void
    {
        DB::transaction(function () use ($product, $mediaId) {
            $media = $product->media()->where('id', $mediaId)->firstOrFail();

            if (Storage::disk('public')->exists($media->file_path)) {
                Storage::disk('public')->delete($media->file_path);
            }

            $wasPrimary = $media->is_primary;
            $media->delete();

            if ($wasPrimary) {
                $nextMedia = $product->media()->first();
                if ($nextMedia) {
                    $nextMedia->update(['is_primary' => true]);
                }
            }
        });
    }
    /**
     * Crear un nuevo producto con sus atributos de ficha técnica.
     */
    public function crear(array $data): Product
    {
        
        $this->validarUnicidadCombinacion($data);
        
        return DB::transaction(function () use ($data) {
            $data['slug'] = Str::slug($data['name'] . '-' . Str::random(5));

            $attributeValueIds = $data['attribute_value_ids'] ?? [];
            unset($data['attribute_value_ids']);

            $product = Product::create($data);

            if (!empty($attributeValueIds)) {
                $product->attributeValues()->sync($attributeValueIds);
            }

            return $product;
        });
    }
    /**
     * Actualizar un producto existente.
     */
    public function actualizar(Product $product, array $data): Product
    {
        $this->validarUnicidadCombinacion($data, $product->id);

        return DB::transaction(function () use ($product, $data) {
            // Si tiene unidades/prendas registradas, bloqueamos cambios en atributos clave de clasificación
            if ($product->physicalProducts()->exists()) {
                unset($data['category_id'], $data['gender'], $data['brand_id']);
            }

            if (isset($data['name']) && $data['name'] !== $product->name) {
                $data['slug'] = Str::slug($data['name'] . '-' . Str::random(5));
            }

            $attributeValueIds = $data['attribute_value_ids'] ?? null;
            unset($data['attribute_value_ids']);

            $product->update($data);

            if (is_array($attributeValueIds)) {
                $product->attributeValues()->sync($attributeValueIds);
            }

            return $product;
        });
    }

    /**
     * Valida que no exista la misma combinación de Categoría + Género + Marca + Nombre.
     */
    protected function validarUnicidadCombinacion(array $data, ?int $ignoreProductId = null): void
    {
        $query = Product::withTrashed()
            ->where('category_id', $data['category_id'] ?? null)
            ->where('gender', $data['gender'] ?? 'unisex')
            ->where('brand_id', $data['brand_id'] ?? null)
            ->where('name', $data['name'] ?? '');

        if ($ignoreProductId) {
            $query->where('id', '!=', $ignoreProductId);
        }

        $existing = $query->first();

        if ($existing) {
            if ($existing->trashed()) {
                throw ValidationException::withMessages([
                    'name' => "Este producto ya existe pero se encuentra en ELIMINADOS. Puedes ir a la lista de 'Ver eliminados' y restaurarlo."
                ]);
            }

            throw ValidationException::withMessages([
                'name' => "Ya existe un producto con la misma Categoría, Género, Marca y Nombre."
            ]);
        }
    }
    /**
     * Guardar o actualizar un producto validando combinaciones únicas.
     */
    public function guardar(array $data, ?int $productId = null): Product
    {
        // 1. Verificar si existe combinación idéntica en la DB
        $query = Product::withTrashed()
            ->where('category_id', $data['category_id'])
            ->where('gender', $data['gender'])
            ->where('brand_id', $data['brand_id'] ?? null)
            ->where('name', $data['name']);

        if ($productId) {
            $query->where('id', '!=', $productId);
        }

        $existingProduct = $query->first();

        if ($existingProduct) {
            if ($existingProduct->trashed()) {
                throw ValidationException::withMessages([
                    'name' => "Este producto ya existe pero se encuentra ELIMINADO. Puedes buscarlo en 'Ver eliminados' y restaurarlo."
                ]);
            }

            throw ValidationException::withMessages([
                'name' => "Ya existe un producto registrado con la misma Categoría, Género, Marca y Nombre."
            ]);
        }

        // 2. Crear o actualizar
        if ($productId) {
            $product = Product::findOrFail($productId);

            // Si el producto ya tiene prendas/ítems físicos asociados, protegemos los campos núcleo
            if ($product->physicalProducts()->exists()) {
                unset($data['category_id'], $data['gender'], $data['brand_id']);
            }

            $product->update($data);
            return $product;
        }

        return Product::create($data);
    }

    public function eliminar(Product $product): bool
    {
        return $product->delete();
    }

    public function restaurar(Product $product): bool
    {
        return $product->restore();
    }
}