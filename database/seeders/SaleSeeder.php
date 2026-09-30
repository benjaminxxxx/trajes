<?php

namespace Database\Seeders;

use App\Models\PhysicalProduct;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Database\Seeder;

class SaleSeeder extends Seeder
{
    use RunsInAccount;

    public function run(): void
    {
        $this->ensureAccount();

        $saleService = app(SaleService::class);
        $user = User::first() ?? User::factory()->create();

        // 2. Obtener prendas físicas en estado disponible
        $availableProducts = PhysicalProduct::where('status', 'available')->get();

        if ($availableProducts->count() < 3) {
            $this->command->warn('Se necesitan al menos 3 prendas disponibles. Ejecuta PurchaseSeeder primero.');

            return;
        }

        $paymentMethods = ['cash', 'yape', 'plin', 'card'];
        $createdSales = [];

        // 3. Crear 3 Ventas
        for ($i = 1; $i <= 3; $i++) {
            $count = rand(1, min(5, $availableProducts->count()));
            $selectedItems = $availableProducts->where('status', 'available')->random($count);

            if ($selectedItems->isEmpty()) {
                break;
            }

            $items = [];
            foreach ($selectedItems as $item) {
                $items[] = [
                    'physical_product_id' => $item->id,
                    'unit_price' => rand(150, 450), // Precio de venta del traje/vestido
                ];
            }

            $hasInvoicePending = ($i === 2); // Simular que la venta 2 requiere factura posterior

            $saleData = [
                'user_id' => $user->id,
                'document_type' => $hasInvoicePending ? 'nota_venta' : 'boleta',
                'document_number' => $hasInvoicePending ? null : "B001-00000{$i}",
                'has_invoice_pending' => $hasInvoicePending,
                'sale_date' => now()->subDays(rand(0, 5)),
                'payment_method' => $paymentMethods[array_rand($paymentMethods)],
                'notes' => "Venta de prueba N° {$i}",
                'items' => $items,
            ];

            $sale = $saleService->createSale($saleData);
            $createdSales[] = $sale;

            // Refrescar la colección de disponibles
            $availableProducts = PhysicalProduct::where('status', 'available')->get();
        }

        // 4. Probar la anulación llamando al servicio sobre la última venta realizada
        if (! empty($createdSales)) {
            $lastSale = end($createdSales);
            $saleService->cancelSale($lastSale, 'Cliente desistió de la compra por insatisfacción con la talla.');
        }
    }
}
