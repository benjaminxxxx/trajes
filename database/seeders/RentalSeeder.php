<?php

namespace Database\Seeders;

use App\Models\PhysicalProduct;
use App\Models\User;
use App\Services\RentalService;
use Illuminate\Database\Seeder;

class RentalSeeder extends Seeder
{
    use RunsInAccount;

    public function run(): void
    {
        $this->ensureAccount();

        $rentalService = app(RentalService::class);

        $user = User::first() ?? User::factory()->create();

        // 2. Tomar prendas físicas disponibles
        $availableProducts = PhysicalProduct::where('status', 'available')->get();

        if ($availableProducts->count() < 3) {
            $this->command->warn('Debes tener al menos 3 productos físicos disponibles (ejecuta PurchaseSeeder primero).');

            return;
        }

        $paymentMethods = ['cash', 'yape', 'plin', 'card'];

        // 3. Generar 5 Alquileres de prueba
        for ($i = 1; $i <= 5; $i++) {
            // Seleccionar entre 1 y 3 prendas al azar
            $itemCount = rand(1, min(3, $availableProducts->count()));
            $selectedProducts = $availableProducts->where('status', 'available')->random($itemCount);

            if ($selectedProducts->isEmpty()) {
                break;
            }

            $items = [];
            $isCombo = false;

            foreach ($selectedProducts as $index => $product) {
                // El primer ítem paga tarifa de alquiler, los subsecuentes pueden ser combos a S/ 0.00
                $price = ($index === 0) ? rand(50, 120) : (rand(0, 1) === 1 ? 0.00 : rand(20, 50));

                $items[] = [
                    'physical_product_id' => $product->id,
                    'price' => $price,
                ];
            }

            $pickupDate = now()->subDays(rand(1, 10));
            $expectedReturn = (clone $pickupDate)->addDays(rand(2, 4));

            $rentalData = [
                'dni' => '98798789',
                'name' => 'clliente',
                'user_id' => $user->id,
                'pickup_date' => $pickupDate->format('Y-m-d H:i:s'),
                'expected_return_date' => $expectedReturn->format('Y-m-d H:i:s'),
                'deposit_amount' => rand(30, 100),
                'payment_method' => $paymentMethods[array_rand($paymentMethods)],
                'notes' => "Alquiler de prueba N° {$i} generado desde el Seeder.",
                'items' => $items,
                'media' => [
                    [
                        'file_path' => 'rentals/dni_client_1.jpg',
                        'type' => 'dni',
                    ],
                ],
            ];

            $rentalService->createRental($rentalData);
        }
    }
}
