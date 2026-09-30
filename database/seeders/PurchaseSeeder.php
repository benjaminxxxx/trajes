<?php

namespace Database\Seeders;

use App\Models\Color;
use App\Models\Product;
use App\Models\Size;
use App\Models\User;
use App\Services\PurchaseService;
use Illuminate\Database\Seeder;

class PurchaseSeeder extends Seeder
{
    use RunsInAccount;

    public function run(): void
    {
        $this->ensureAccount();

        $purchaseService = app(PurchaseService::class);

        $user = User::first() ?? User::factory()->create();
        $products = Product::all();
        $colors = Color::all();
        $sizes = Size::all();

        if ($products->isEmpty() || $colors->isEmpty() || $sizes->isEmpty()) {
            $this->command->warn('Debes ejecutar ProductSeeder, ColorSeeder y SizeSeeder primero.');

            return;
        }

        $documentTypes = ['nota_venta', 'boleta', 'factura'];

        // 2. Generar 5 Compras
        for ($i = 1; $i <= 5; $i++) {
            $docType = $documentTypes[array_rand($documentTypes)];
            $detailCount = rand(1, 6); // Entre 1 y 6 detalles por compra
            $items = [];

            for ($j = 0; $j < $detailCount; $j++) {
                $product = $products->random();
                $color = $colors->random();
                $size = $sizes->random();

                $items[] = [
                    'product_id' => $product->id,
                    'color_id' => $color->id,
                    'size_id' => $size->id,
                    'quantity' => rand(1, 3), // De 1 a 3 vestidos/trajes por ítem
                    'unit_price' => rand(80, 200), // Precio pactado de compra
                ];
            }

            $purchaseData = [
                'supplier_id' => null,
                'user_id' => $user->id,
                'document_type' => $docType,
                'document_number' => $docType === 'factura' ? "F001-00000{$i}" : "NV-000{$i}",
                'has_invoice_pending' => $docType === 'nota_venta' && rand(0, 1) === 1,
                'issue_date' => now()->subDays(rand(1, 30))->format('Y-m-d'),
                'notes' => "Compra de prueba N° {$i} cargada desde Seeder.",
                'items' => $items,
            ];

            $purchaseService->createPurchase($purchaseData);
        }
    }
}
