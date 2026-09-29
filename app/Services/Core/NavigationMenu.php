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

            $menu[] = [
                ...$entry,
                'items' => $items,
                'current' => collect($items)->contains('current', true),
            ];
        }

        return $menu;
    }

    /**
     * Accesos directos a Vender y Alquilar. Cada uno se oculta dentro de su
     * propio módulo (en Ventas solo se ofrece Alquilar, y al revés).
     *
     * @return array<int, array{label: string, icon: string, route: string}>
     */
    public function quickActions(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $actions = [
            ['label' => 'Vender', 'icon' => 'shopping-cart', 'route' => 'sale.sales.create', 'module' => 'sale.*', 'permission' => 'ventas.registrar'],
            ['label' => 'Alquilar', 'icon' => 'sparkles', 'route' => 'rental.rentals.create', 'module' => 'rental.*', 'permission' => 'alquileres.registrar'],
        ];

        return array_values(array_filter(
            $actions,
            fn (array $action) => $user->can($action['permission']) && ! request()->routeIs($action['module']),
        ));
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
