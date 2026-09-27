<?php

namespace App\Livewire\Shared;

use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Lógica común a cualquier formulario que arma una lista de líneas por
 * producto (compra, venta, alquiler): agregar/quitar/duplicar filas, resolver
 * las tallas/colores disponibles por categoría, y avisar (sin bloquear)
 * cuando dos filas repiten producto+talla+color.
 *
 * El componente que use este trait debe declarar `public array $items = [];`
 * y decidir él mismo la forma de cada item (los campos varían: una compra
 * necesita product_id, una venta necesita product_variant_id ya resuelto en
 * el listado, etc.), este trait solo opera sobre el array por clave.
 */
trait ManagesCartItems
{
    protected array $categoryOptionsCache = [];

    protected function addCartItem(array $item): string
    {
        $key = (string) Str::uuid();
        $this->items[$key] = $item;

        return $key;
    }

    public function removeItem(string $key): void
    {
        unset($this->items[$key]);
    }

    public function duplicateItem(string $key): void
    {
        if (isset($this->items[$key])) {
            $this->items[(string) Str::uuid()] = $this->items[$key];
        }
    }

    /**
     * Tallas/colores habilitados para la categoría del producto (ver
     * category_size / category_color), resueltos una sola vez por request.
     */
    public function sizeOptions(?int $categoryId): Collection
    {
        return $this->categoryOptions($categoryId)['sizes'];
    }

    public function colorOptions(?int $categoryId): Collection
    {
        return $this->categoryOptions($categoryId)['colors'];
    }

    protected function categoryOptions(?int $categoryId): array
    {
        if (! $categoryId) {
            return ['sizes' => collect(), 'colors' => collect()];
        }

        if (! isset($this->categoryOptionsCache[$categoryId])) {
            $category = Category::with('sizes', 'colors')->find($categoryId);

            $this->categoryOptionsCache[$categoryId] = [
                'sizes' => $category?->sizes ?? collect(),
                'colors' => $category?->colors ?? collect(),
            ];
        }

        return $this->categoryOptionsCache[$categoryId];
    }

    /**
     * Convierte los items del borrador (strings vacíos de los <select>,
     * números como string desde los inputs) a los tipos que esperan los
     * servicios de escritura. Se usa justo antes de guardar.
     */
    protected function normalizedLines(): array
    {
        return array_map(function (array $item) {
            $item['size_id'] = ($item['size_id'] ?? '') !== '' ? (int) $item['size_id'] : null;
            $item['color_id'] = ($item['color_id'] ?? '') !== '' ? (int) $item['color_id'] : null;
            $item['quantity'] = (int) ($item['quantity'] ?? 1);
            $item['unit_price'] = (float) ($item['unit_price'] ?? 0);
            $item['discount_percent'] = (float) ($item['discount_percent'] ?? 0);

            return $item;
        }, array_values($this->items));
    }

    /**
     * Aviso NO bloqueante: hay otra fila con el mismo producto+talla+color.
     * Puede ser intencional (ej. últimas unidades con precio distinto), así
     * que solo se señala, nunca se impide guardar.
     */
    public function isDuplicateVariant(string $key): bool
    {
        $item = $this->items[$key] ?? null;

        if (! $item || empty($item['size_id']) || empty($item['color_id'])) {
            return false;
        }

        foreach ($this->items as $otherKey => $other) {
            if ($otherKey === $key) {
                continue;
            }

            if ((int) ($other['product_id'] ?? 0) === (int) $item['product_id']
                && (int) $other['size_id'] === (int) $item['size_id']
                && (int) $other['color_id'] === (int) $item['color_id']) {
                return true;
            }
        }

        return false;
    }
}