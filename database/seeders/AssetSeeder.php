<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\Category;
use App\Models\DepreciationRule;
use App\Services\Assets\AssetService;
use Illuminate\Database\Seeder;

/**
 * Bienes típicos de una tienda de alquiler de trajes y las reglas de desgaste
 * por defecto. Idempotente: no duplica bienes ni reglas existentes.
 */
class AssetSeeder extends Seeder
{
    use RunsInAccount;

    public function run(AssetService $service): void
    {
        $this->ensureAccount();

        foreach ([[3, 10], [5, 25], [10, 40]] as [$minRentals, $percent]) {
            DepreciationRule::firstOrCreate(['min_rentals' => $minRentals], ['percent' => $percent]);
        }

        $suits = ['Ternos y Esmóquines', 'Trajes de Niño'];
        $dresses = ['Vestidos de Dama', 'Vestidos de Niña'];
        $shoes = ['Calzado de Varón', 'Calzado de Dama', 'Calzado Infantil'];

        // [nombre, tipo, cantidad, costo unitario, valor actual (null = costo), categorías si se entrega con la venta]
        $assets = [
            ['Barandal colgador cromado', 'exhibicion', 6, 180, 150, null],
            ['Mesa de exhibición de melamina', 'mobiliario', 2, 320, 260, null],
            ['Escritorio de atención', 'mobiliario', 1, 450, 380, null],
            ['Silla de atención', 'mobiliario', 3, 90, null, null],
            ['Espejo de cuerpo entero', 'mobiliario', 2, 250, null, null],
            ['Maniquí de caballero', 'exhibicion', 3, 280, null, null],
            ['Maniquí de dama', 'exhibicion', 3, 260, null, null],
            ['Maniquí infantil', 'exhibicion', 2, 150, null, null],
            ['Colgador de pie', 'exhibicion', 4, 75, null, null],
            ['Ganchos de madera para terno', 'exhibicion', 20, 3, null, null],
            ['Ganchos de plástico', 'exhibicion', 100, 0.8, null, null],
            ['Planchador a vapor', 'herramienta', 1, 390, 300, null],
            ['Tabla de planchar', 'herramienta', 1, 120, null, null],
            ['Máquina de coser', 'herramienta', 1, 850, 700, null],
            ['Portaterno', 'empaque', 50, 6, null, $suits],
            ['Portavestido', 'empaque', 30, 7, null, $dresses],
            ['Correa para vestido', 'accesorio', 40, 4, null, $dresses],
            ['Bolsa de papel con logo', 'empaque', 200, 1.2, null, []],
            ['Caja para calzado', 'empaque', 30, 2.5, null, $shoes],
        ];

        foreach ($assets as [$name, $type, $quantity, $unitCost, $currentValue, $deliveryCategories]) {
            if (Asset::withTrashed()->where('name', $name)->exists()) {
                continue;
            }

            $deliversWithSale = $deliveryCategories !== null;

            $service->create([
                'name' => $name,
                'type' => $type,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'current_unit_value' => $currentValue,
                'is_consumable' => $deliversWithSale,
                'delivers_with_sale' => $deliversWithSale,
            ], Category::whereIn('name', $deliveryCategories ?? [])->pluck('id')->all());
        }
    }
}
