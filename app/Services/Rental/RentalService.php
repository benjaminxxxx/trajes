<?php

namespace App\Services\Rental;

use App\Models\CashMovement;
use App\Models\InventoryMovement;
use App\Models\PhysicalProduct;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Rental;
use App\Models\RentalDetail;
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

    /**
     * Anula el alquiler sin borrarlo: las prendas que siguen fuera por este
     * alquiler vuelven a `available` y, si hubo garantía, se registra la
     * salida de caja que la devuelve.
     */
    public function cancel(Rental $rental, string $reason): Rental
    {
        return DB::transaction(function () use ($rental, $reason) {
            $rental = Rental::whereKey($rental->id)->lockForUpdate()->firstOrFail();

            if (! $rental->isCancellable()) {
                throw new \RuntimeException("El alquiler {$rental->code} no se puede anular en su estado actual.");
            }

            PhysicalProduct::whereIn('id', $rental->details()->pluck('physical_product_id'))
                ->where('status', 'rented')
                ->update(['status' => 'available']);

            if ((float) $rental->deposit_amount > 0) {
                CashMovement::create([
                    'user_id' => Auth::id(),
                    'type' => CashMovement::TYPE_EXPENSE,
                    'origin' => CashMovement::ORIGIN_RENTAL_PAYMENT,
                    'amount' => $rental->deposit_amount,
                    'payment_method' => 'cash',
                    'reference_id' => $rental->id,
                    'reference_type' => Rental::class,
                    'description' => "Devolución de garantía por anulación {$rental->code}",
                ]);
            }

            $rental->update([
                'status' => Rental::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => Auth::id(),
                'cancellation_reason' => $reason,
            ]);

            return $rental;
        });
    }

    /**
     * Recepciona (cierra) un alquiler en curso.
     *
     * $data = [
     *   'actual_return_date' => fecha/hora de recepción,
     *   'return_notes' => ?string (observaciones: dificultades con el cliente, etc.),
     *   'items' => [rental_detail_id => [
     *       'condition' => good|dirty|damaged|lost,
     *       'damage_fee' => float (lo que se le cobra al cliente por daño o pérdida),
     *       'damage_note' => ?string,
     *       'next_status' => available|maintenance (ignorado si se perdió),
     *   ]],
     * ]
     *
     * El cobro por daño es un ingreso (caja + historial de la prenda),
     * independiente de lo que luego cueste repararla. La garantía se devuelve
     * en mano y no se registra aquí.
     */
    public function receive(Rental $rental, array $data): Rental
    {
        return DB::transaction(function () use ($rental, $data) {
            $rental = Rental::whereKey($rental->id)->lockForUpdate()->firstOrFail();

            if ($rental->status !== Rental::STATUS_OUT) {
                throw new \RuntimeException("El alquiler {$rental->code} no está en curso, no se puede recepcionar.");
            }

            $penaltyTotal = 0.0;

            foreach ($rental->details()->with('physicalProduct.variant')->get() as $detail) {
                $item = $data['items'][$detail->id] ?? throw new \RuntimeException('Falta indicar el estado de una de las prendas.');
                $condition = $item['condition'];
                $fee = round(max(0, (float) ($item['damage_fee'] ?? 0)), 2);
                $penaltyTotal += $fee;

                $detail->update([
                    'return_condition' => $condition,
                    'damage_fee' => $fee,
                    'damage_note' => filled($item['damage_note'] ?? null) ? trim($item['damage_note']) : null,
                ]);

                $this->settleUnit($detail->physicalProduct, $condition, $item['next_status'] ?? 'available', $rental);
            }

            $rental->update([
                'status' => Rental::STATUS_RETURNED,
                'actual_return_date' => $data['actual_return_date'],
                'return_notes' => filled($data['return_notes'] ?? null) ? trim($data['return_notes']) : null,
                'penalty_total' => round($penaltyTotal, 2),
            ]);

            if ($penaltyTotal > 0) {
                CashMovement::create([
                    'user_id' => Auth::id(),
                    'type' => CashMovement::TYPE_INCOME,
                    'origin' => CashMovement::ORIGIN_PENALTY_FEE,
                    'amount' => round($penaltyTotal, 2),
                    'payment_method' => $data['payment_method'] ?? 'cash',
                    'reference_id' => $rental->id,
                    'reference_type' => Rental::class,
                    'description' => "Cobro por daños al recepcionar {$rental->code}",
                ]);
            }

            return $rental;
        });
    }

    /**
     * Deja la prenda en su nuevo estado al volver del alquiler. Si se perdió,
     * sale del inventario (baja con fecha y movimiento de salida).
     */
    protected function settleUnit(?PhysicalProduct $unit, string $condition, string $nextStatus, Rental $rental): void
    {
        if (! $unit || $unit->status !== 'rented') {
            return;
        }

        if ($condition === 'lost') {
            $unit->update(['status' => 'lost', 'status_note' => "No devuelta en el alquiler {$rental->code}"]);

            ProductVariant::whereKey($unit->product_variant_id)->where('stock', '>', 0)->decrement('stock');

            InventoryMovement::create([
                'product_variant_id' => $unit->product_variant_id,
                'physical_product_id' => $unit->id,
                'user_id' => Auth::id(),
                'type' => InventoryMovement::TYPE_OUT,
                'concept' => InventoryMovement::CONCEPT_LOST,
                'quantity' => 1,
                'unit_cost' => (float) $unit->purchase_cost,
                'total_cost' => (float) $unit->purchase_cost,
                'reference_id' => $rental->id,
                'reference_type' => Rental::class,
                'notes' => "Prenda no devuelta en el alquiler {$rental->code}",
            ]);

            return;
        }

        $unit->update([
            'status' => $nextStatus === 'maintenance' ? 'maintenance' : 'available',
            'status_note' => $nextStatus === 'maintenance' ? "Devuelta {$this->conditionLabel($condition)} del alquiler {$rental->code}" : null,
        ]);
    }

    protected function conditionLabel(string $condition): string
    {
        return mb_strtolower(RentalDetail::CONDITION_LABELS[$condition] ?? $condition);
    }

    protected function registerLine(Rental $rental, array $line): void
    {
        // Prenda puntual elegida por su código en el selector de prendas.
        if (! empty($line['physical_product_id'])) {
            $physical = PhysicalProduct::whereKey($line['physical_product_id'])->lockForUpdate()->first();

            if (! $physical || $physical->status !== 'available') {
                throw new \RuntimeException('La prenda '.($physical->code ?? '#'.$line['physical_product_id']).' ya no está disponible.');
            }

            $physical->update(['status' => 'rented']);

            $rental->details()->create([
                'physical_product_id' => $physical->id,
                'price' => (float) $line['unit_price'],
            ]);

            return;
        }

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
