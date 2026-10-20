<?php

namespace App\Services\Inventory;

use App\Models\Category;
use App\Models\Color;
use App\Models\ProductCharacteristic;
use App\Models\ProductTag;
use App\Models\Size;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Búsqueda de productos por palabras: "mujer rojo L quinceañera" devuelve lo
 * que cumple TODAS las palabras, y cada palabra puede coincidir con el
 * nombre, código, categoría, género, marca, característica (Satín), etiqueta
 * manual, o la talla/color de alguna variante.
 *
 * Las palabras cortas (L, M, 40) solo coinciden exactas con una talla o una
 * etiqueta: "L" no debe traer todo lo que tenga una "l" en el nombre.
 */
class ProductSearch
{
    /**
     * Palabras que equivalen a un género.
     *
     * @var array<string, array<int, string>>
     */
    public const GENDER_WORDS = [
        'mujer' => ['femenino'], 'mujeres' => ['femenino'], 'dama' => ['femenino'], 'damas' => ['femenino'],
        'femenino' => ['femenino'], 'señora' => ['femenino'], 'señoras' => ['femenino'],
        'hombre' => ['masculino'], 'hombres' => ['masculino'], 'varon' => ['masculino'], 'varón' => ['masculino'],
        'caballero' => ['masculino'], 'caballeros' => ['masculino'], 'masculino' => ['masculino'],
        'niño' => ['nino'], 'niña' => ['nino'], 'niños' => ['nino'], 'niñas' => ['nino'], 'nino' => ['nino'],
        'nina' => ['nino'], 'infantil' => ['nino'], 'unisex' => ['unisex'],
    ];

    public const SHORT_WORD = 3;

    /**
     * @return array<int, string>
     */
    public function words(string $term): array
    {
        return collect(preg_split('/[\s,]+/u', Str::squish($term)))
            ->filter()
            ->map(fn (string $word) => mb_strtolower($word))
            ->unique()
            ->take(8)
            ->values()
            ->all();
    }

    public function apply(Builder $query, string $term): Builder
    {
        foreach ($this->words($term) as $word) {
            $query->where(fn (Builder $q) => $this->matchWord($q, $word));
        }

        return $query;
    }

    protected function matchWord(Builder $query, string $word): void
    {
        $isShort = mb_strlen($word) < self::SHORT_WORD;
        $like = "%{$word}%";

        $query->whereHas('variants.size', fn (Builder $q) => $q->whereRaw('LOWER(name) = ?', [$word]))
            ->orWhereHas('tags', fn (Builder $q) => $isShort
                ? $q->whereRaw('LOWER(name) = ?', [$word])
                : $q->whereRaw('LOWER(name) LIKE ?', [$like]));

        if (isset(self::GENDER_WORDS[$word])) {
            // "Señora" también puede ser una etiqueta: el género suma, no reemplaza.
            $query->orWhereIn('gender', self::GENDER_WORDS[$word]);
        }

        if ($isShort) {
            $query->orWhereRaw('LOWER(code) = ?', [$word]);

            return;
        }

        $query->orWhereRaw('LOWER(name) LIKE ?', [$like])
            ->orWhereRaw('LOWER(code) LIKE ?', [$like])
            ->orWhereHas('category', fn (Builder $q) => $q->whereRaw('LOWER(name) LIKE ?', [$like]))
            ->orWhereHas('brand', fn (Builder $q) => $q->whereRaw('LOWER(name) LIKE ?', [$like]))
            ->orWhereHas('characteristics', fn (Builder $q) => $q->whereRaw('LOWER(value) LIKE ?', [$like]))
            ->orWhereHas('variants.color', fn (Builder $q) => $q->whereRaw('LOWER(name) LIKE ?', [$like]));
    }

    /**
     * Cómo se entendió cada palabra, para mostrarlo como chips:
     * [['word' => 'rojo', 'kind' => 'Color'], ['word' => 'l', 'kind' => 'Talla']].
     *
     * @return array<int, array{word: string, kind: string}>
     */
    public function interpret(string $term): array
    {
        return array_map(fn (string $word) => ['word' => $word, 'kind' => $this->kindOf($word)], $this->words($term));
    }

    protected function kindOf(string $word): string
    {
        $isShort = mb_strlen($word) < self::SHORT_WORD;
        $like = "%{$word}%";

        return match (true) {
            isset(self::GENDER_WORDS[$word]) => 'Género',
            Size::whereRaw('LOWER(name) = ?', [$word])->exists() => 'Talla',
            ProductTag::whereRaw($isShort ? 'LOWER(name) = ?' : 'LOWER(name) LIKE ?', [$isShort ? $word : $like])->exists() => 'Etiqueta',
            $isShort => 'Código',
            Color::whereRaw('LOWER(name) LIKE ?', [$like])->exists() => 'Color',
            Category::whereRaw('LOWER(name) LIKE ?', [$like])->exists() => 'Categoría',
            ProductCharacteristic::whereRaw('LOWER(value) LIKE ?', [$like])->exists() => 'Característica',
            default => 'Texto',
        };
    }

    /**
     * Etiquetas manuales ya usadas en el negocio más algunas comunes, para
     * sugerir al escribir y no terminar con "Quinceañera" y "quinceanera".
     *
     * @return array<int, string>
     */
    public function tagSuggestions(): array
    {
        $common = ['Quinceañera', 'Juvenil', 'Señora', 'Boda', 'Novia', 'Madrina', 'Graduación', 'Promoción', 'Gala', 'Bautizo', 'Primera comunión', 'Paje', 'Talla grande'];

        return collect(ProductTag::query()->distinct()->orderBy('name')->pluck('name'))
            ->merge($common)
            ->unique(fn (string $tag) => mb_strtolower($tag))
            ->values()
            ->all();
    }
}
