<?php

namespace App\Livewire\Shared;

use App\Models\Category;
use App\Models\PhysicalProduct;
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

    /**
     * Agrega las líneas elegidas en el ProductPicker. Una prenda física es una
     * fila propia (se ignora si ya está en el carrito); un producto sin prenda
     * física suma su cantidad a la fila de la misma variante, si existe.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @param  callable(array<string, mixed>): array<string, mixed>|null  $prepare  ajusta cada fila nueva (ej. line_total)
     */
    protected function addPickedItems(array $items, ?callable $prepare = null): void
    {
        $prepare ??= fn (array $item) => $item;

        foreach ($items as $item) {
            if (! empty($item['physical_product_id'])) {
                $inCart = collect($this->items)->contains(
                    fn (array $row) => (int) ($row['physical_product_id'] ?? 0) === (int) $item['physical_product_id']
                );

                if (! $inCart) {
                    $this->addCartItem($prepare([...$item, 'quantity' => 1, 'discount_percent' => 0]));
                }

                continue;
            }

            $existingKey = collect($this->items)->search(fn (array $row) => empty($row['physical_product_id'])
                && (int) ($row['product_id'] ?? 0) === (int) $item['product_id']
                && (int) ($row['size_id'] ?? 0) === (int) ($item['size_id'] ?? 0)
                && (int) ($row['color_id'] ?? 0) === (int) ($item['color_id'] ?? 0));

            if ($existingKey !== false) {
                $this->items[$existingKey]['quantity'] = (int) $this->items[$existingKey]['quantity'] + (int) $item['quantity'];
                $this->items[$existingKey] = $prepare($this->items[$existingKey]);

                continue;
            }

            $this->addCartItem($prepare([...$item, 'discount_percent' => 0]));
        }
    }

    /**
     * Separaciones de las prendas físicas del carrito, para avisar (sin
     * bloquear) cuando una prenda está separada para otro cliente.
     *
     * @return Collection<int, PhysicalProduct> indexadas por id de prenda
     */
    public function cartReservations(): Collection
    {
        $unitIds = collect($this->items)->pluck('physical_product_id')->filter()->all();

        if ($unitIds === []) {
            return collect();
        }

        return PhysicalProduct::whereKey($unitIds)
            ->reserved()
            ->with('reservedClient:id,display_name')
            ->get(['id', 'reserved_client_id', 'reserved_until', 'reservation_note'])
            ->keyBy('id');
    }

    public function removeItem(string $key): void
    {
        unset($this->items[$key]);
    }

    public function duplicateItem(string $key): void
    {
        // Una prenda física es única: no se puede duplicar su fila.
        if (isset($this->items[$key]) && empty($this->items[$key]['physical_product_id'])) {
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
                'sizes' => $category?->sizes->where('is_active', true)->values() ?? collect(),
                'colors' => $category?->colors->where('is_active', true)->values() ?? collect(),
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
            $item['physical_product_id'] = ! empty($item['physical_product_id']) ? (int) $item['physical_product_id'] : null;

            if (array_key_exists('line_total', $item)) {
                $item['line_total'] = ($item['line_total'] ?? '') !== '' ? (float) $item['line_total'] : null;
            }

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

        if (! $item || ! empty($item['physical_product_id']) || empty($item['size_id']) || empty($item['color_id'])) {
            return false;
        }

        foreach ($this->items as $otherKey => $other) {
            if ($otherKey === $key) {
                continue;
            }

            if (empty($other['physical_product_id'])
                && (int) ($other['product_id'] ?? 0) === (int) $item['product_id']
                && (int) $other['size_id'] === (int) $item['size_id']
                && (int) $other['color_id'] === (int) $item['color_id']) {
                return true;
            }
        }

        return false;
    }
}
