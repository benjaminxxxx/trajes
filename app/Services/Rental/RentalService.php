<?php

namespace App\Services\Rental;

use App\Models\CashMovement;
use App\Models\PhysicalProduct;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Rental;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Único punto de escritura para alquileres. A diferencia de la venta, el
 * alquiler NO descuenta `product_variants.stock` (la prenda sigue siendo
 * inventario de la empresa, solo está temporalmente afuera): lo que cambia
 * es el `status` de cada unidad física puntual, de `available` a `rented`,
 * para poder rastrear devolución/daños por prenda.
 *
 * `rental_details` no tiene columna `quantity` (cada fila = una prenda), así
 * que una línea del carrito con quantity=3 se expande en 3 unidades físicas
 * distintas de la misma variante, cada una con su propio detail.
 */
class RentalService
{
    public function register(array $header, array $lines): Rental
    {
        return DB::transaction(function () use ($header, $lines) {
            $rentalTotal = round(array_sum(array_map(
                fn ($l) => (float) $l['unit_price'] * (int) ($l['quantity'] ?? 1),
                $lines
            )), 2);

            $rental = Rental::create([
                'code' => $this->generateCode(),
                'client_id' => $header['client_id'],
                'user_id' => $header['user_id'] ?? Auth::id(),
                'pickup_date' => $header['pickup_date'],
                'expected_return_date' => $header['expected_return_date'],
                'rental_total' => $rentalTotal,
                'deposit_amount' => $header['deposit_amount'] ?? 0,
                'status' => Rental::STATUS_OUT,
                'notes' => $header['notes'] ?? null,
            ]);

            foreach ($lines as $line) {
                $this->registerLine($rental, $line);
            }

            $deposit = (float) ($header['deposit_amount'] ?? 0);

            if ($deposit > 0) {
                CashMovement::create([
                    'user_id' => Auth::id(),
                    'type' => CashMovement::TYPE_INCOME,
                    'origin' => CashMovement::ORIGIN_RENTAL_PAYMENT,
                    'amount' => $deposit,
                    'payment_method' => $header['payment_method'] ?? 'cash',
                    'reference_id' => $rental->id,
                    'reference_type' => Rental::class,
                    'description' => "Garantía/adelanto alquiler {$rental->code}",
                ]);
            }

            return $rental->load('details.physicalProduct');
        });
    }

    protected function registerLine(Rental $rental, array $line): void
    {
        /** @var Product $product */
        $product = Product::findOrFail($line['product_id']);

        $variant = ProductVariant::where('product_id', $product->id)
            ->where('color_id', $line['color_id'] ?? null)
            ->where('size_id', $line['size_id'] ?? null)
            ->first();

        if (! $variant) {
            throw new \RuntimeException("No existe una variante de \"{$product->name}\" con esa talla/color.");
        }

        $qty = max(1, (int) ($line['quantity'] ?? 1));

        for ($i = 0; $i < $qty; $i++) {
            // Bloqueo pesimista sobre la unidad física para que dos
            // alquileres simultáneos no se lleven la misma prenda.
            $physical = PhysicalProduct::where('product_variant_id', $variant->id)
                ->where('status', 'available')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (! $physical) {
                throw new \RuntimeException("No hay suficientes unidades disponibles de \"{$product->name}\" en esa talla/color (pediste {$qty}).");
            }

            $physical->update(['status' => 'rented']);

            $rental->details()->create([
                'physical_product_id' => $physical->id,
                'price' => (float) $line['unit_price'],
            ]);
        }
    }

    protected function generateCode(): string
    {
        $today = now()->format('ymd');
        $sequence = Rental::whereDate('created_at', now())->count() + 1;

        return "A-{$today}-".str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}