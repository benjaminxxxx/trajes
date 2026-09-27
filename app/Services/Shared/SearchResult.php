<?php

namespace App\Services\Shared;

/**
 * Forma común de un resultado, sin importar de qué modelo venga.
 * `subtitle` es lo que va debajo del nombre (RUC, SKU, DNI...) y
 * `initials`/`badgeColor` alimentan el avatar en la lista de resultados.
 */
final class SearchResult
{
    public function __construct(
        public readonly int $id,
        public readonly string $label,
        public readonly ?string $subtitle = null,
        public readonly ?string $initials = null,
        public readonly string $badgeColor = 'bg-violet-100 text-violet-700 dark:bg-violet-950/60 dark:text-violet-300',
        public readonly array $meta = [],
    ) {
    }

    public static function fromName(int $id, string $name, ?string $subtitle = null, ?string $badgeColor = null, array $meta = []): self
    {
        $words = preg_split('/\s+/', trim($name));
        $initials = strtoupper(substr($words[0] ?? '', 0, 1).substr($words[1] ?? '', 0, 1));

        return new self(
            id: $id,
            label: $name,
            subtitle: $subtitle,
            initials: $initials ?: null,
            badgeColor: $badgeColor ?? 'bg-violet-100 text-violet-700 dark:bg-violet-950/60 dark:text-violet-300',
            meta: $meta,
        );
    }
}