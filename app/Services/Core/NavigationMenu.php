<?php

namespace App\Services\Core;

use App\Models\User;

/**
 * Filtra config/menu.php según los permisos del usuario y marca la opción
 * actual. Lo usan el header de escritorio y el menú lateral del celular.
 */
class NavigationMenu
{
    /**
     * @return array<int, array{label: string, icon: string, route?: string, current: bool, items?: array<int, array<string, mixed>>}>
     */
    public function for(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $menu = [];

        foreach (config('menu') as $entry) {
            if (! isset($entry['items'])) {
                if ($this->allows($user, $entry)) {
                    $menu[] = [...$entry, 'current' => $this->isCurrent($entry)];
                }

                continue;
            }

            $items = array_values(array_filter($entry['items'], fn (array $item) => $this->allows($user, $item)));

            if ($items === []) {
                continue;
            }

            $items = array_map(fn (array $item) => [...$item, 'current' => $this->isCurrent($item)], $items);

            // El primer separador visible no tiene sentido (quedaría arriba del todo).
            $items[0]['separator'] = false;

            $menu[] = [
                ...$entry,
                'items' => $items,
                'current' => collect($items)->contains('current', true),
            ];
        }

        return $menu;
    }

    protected function allows(User $user, array $entry): bool
    {
        return empty($entry['permission']) || $user->can($entry['permission']);
    }

    protected function isCurrent(array $entry): bool
    {
        return request()->routeIs(...($entry['active'] ?? [$entry['route']]));
    }
}
