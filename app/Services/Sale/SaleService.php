<?php

namespace App\Services\Sale;

use App\Models\CashMovement;
use App\Models\InventoryMovement;
use App\Models\PhysicalProduct;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Services\Assets\AssetService;
use App\Services\Billing\BillingService;
use App\Services\Finance\CashService;
use App\Services\Rental\ReservationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Único punto de escritura para ventas: descuenta stock de la variante,
 * consume la unidad física elegida (o la más antigua disponible) si el
 * producto se rastrea individualmente, registra el movimiento de inventario
 * y el ingreso en caja. Simétrico a PurchaseService pero en sentido inverso.
 *
 * Los precios de cada línea llegan CON IGV (es lo que se le dice al cliente:
 * "150 el terno"); el IGV se desglosa hacia atrás. En sale_details se guardan
 * precio y subtotal SIN IGV para compararlos directo con unit_cost (margen).
 *
 * Cada línea llega como:
 * [
 *   'product_id' => int, 'size_id' => ?int, 'color_id' => ?int,
 *   'quantity' => int, 'unit_price' => float (con IGV),
 *   'line_total' => ?float (con IGV; si viene, manda sobre quantity × unit_price),
 *   'physical_product_id' => ?int (prenda puntual elegida; fuerza quantity = 1),
 * ]
 */
class SaleService
{
    public const TAX_RATE = 0.18;

    /**
     * @param  array<int|string, int|array{asset_id: int, quantity: int, unit_price?: float}>  $deliveries  bienes que salen con la venta:
     *                                                                                                      regalados (asset_id => cantidad) o vendidos (con unit_price, IGV incluido)
     */
    public function register(array $header, array $lines, array $deliveries = []): Sale
    {
        return DB::transaction(function () use ($header, $lines, $deliveries) {
            $deliveries = app(AssetService::class)->normalizeDeliveries($deliveries);

            $total = round(
                array_sum(array_map(fn (array $line) => $this->lineGross($line), $lines))
                + array_sum(array_map(fn (array $delivery) => $delivery['quantity'] * $delivery['unit_price'], $deliveries)),
                2
            );
            $subtotal = round($total / (1 + self::TAX_RATE), 2);
            $tax = round($total - $subtotal, 2);

            $sale = Sale::create([
                'code' => $this->generateCode(),
                'client_id' => $header['client_id'] ?? null,
                'user_id' => $header['user_id'] ?? Auth::id(),
                'document_type' => $header['document_type'],
                'document_number' => $header['document_number'] ?? null,
                'has_invoice_pending' => $header['has_invoice_pending'] ?? false,
                'sale_date' => $header['sale_date'],
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total,
                'status' => Sale::STATUS_COMPLETED,
                'notes' => $header['notes'] ?? null,
            ]);

            foreach ($lines as $line) {
                $this->registerLine($sale, $line);
            }

            app(AssetService::class)->deliverForSale($sale, $deliveries);

            app(CashService::class)->income(CashMovement::ORIGIN_SALE_PAYMENT, $total, "Venta {$sale->code}", $sale, [
                'payment_method' => $header['payment_method'] ?? null,
            ]);

            return $sale->load('details.variant', 'details.physicalProduct');
        });
    }

    /**
     * Anula la venta sin borrarla: devuelve el stock de cada variante, libera
     * la prenda física vendida, deja el movimiento de inventario de entrada y
     * registra la salida de caja que compensa el ingreso original.
     */
    public function cancel(Sale $sale, string $reason): Sale
    {
        return DB::transaction(function () use ($sale, $reason) {
            $sale = Sale::whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if ($sale->isCancelled()) {
                throw new \RuntimeException("La venta {$sale->code} ya está anulada.");
            }

            foreach ($sale->details()->with('variant', 'physicalProduct')->get() as $detail) {
                $detail->variant->blendAverageCost($detail->quantity, (float) $detail->unit_cost);
                $detail->variant->increment('stock', $detail->quantity);

                if ($detail->physicalProduct?->status === 'sold') {
                    $detail->physicalProduct->update([
                        'status' => 'available',
                        'sold_at' => null,
                        'final_sale_price' => null,
                    ]);
                }

                InventoryMovement::create([
                    'product_variant_id' => $detail->product_variant_id,
                    'physical_product_id' => $detail->physical_product_id,
                    'user_id' => Auth::id(),
                    'type' => InventoryMovement::TYPE_IN,
                    'concept' => InventoryMovement::CONCEPT_SALE_CANCELLED,
                    'quantity' => $detail->quantity,
                    'unit_cost' => $detail->unit_cost,
                    'total_cost' => round((float) $detail->unit_cost * $detail->quantity, 2),
                    'reference_id' => $detail->id,
                    'reference_type' => SaleDetail::class,
                    'notes' => "Anulación de la venta {$sale->code}",
                ]);
            }

            app(AssetService::class)->returnForSale($sale);

            app(CashService::class)->expense(CashMovement::ORIGIN_SALE_PAYMENT, (float) $sale->total, "Anulación venta {$sale->code}", $sale);

            $sale->update([
                'status' => Sale::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => Auth::id(),
                'cancellation_reason' => $reason,
            ]);

            // Aún no se emiten notas de crédito: si ya había factura/boleta en SUNAT, queda marcada
            // como pendiente de nota de crédito para que el negocio la emita.
            app(BillingService::class)->flagCreditNote($sale);

            return $sale;
        });
    }

    /**
     * Importe de la línea CON IGV.
     */
    protected function lineGross(array $line): float
    {
        if (isset($line['line_total']) && $line['line_total'] !== '') {
            return round((float) $line['line_total'], 2);
        }

        $qty = ! empty($line['physical_product_id']) ? 1 : (int) $line['quantity'];
        $discount = (float) ($line['discount_percent'] ?? 0);

        return round($qty * (float) $line['unit_price'] * (1 - $discount / 100), 2);
    }

    protected function registerLine(Sale $sale, array $line): SaleDetail
    {
        $physical = null;

        if (! empty($line['physical_product_id'])) {
            // Prenda puntual elegida por el usuario (ej. un terno que ya se alquiló).
            $physical = PhysicalProduct::whereKey($line['physical_product_id'])->lockForUpdate()->first();

            if (! $physical || $physical->status !== 'available') {
                throw new \RuntimeException('La prenda '.($physical->code ?? '#'.$line['physical_product_id']).' ya no está disponible.');
            }

            $variant = ProductVariant::whereKey($physical->product_variant_id)->lockForUpdate()->firstOrFail();
            $product = $variant->product;
            $qty = 1;
        } else {
            /** @var Product $product */
            $product = Product::findOrFail($line['product_id']);
            $qty = (int) $line['quantity'];

            $variant = ProductVariant::where('product_id', $product->id)
                ->where('color_id', $line['color_id'] ?? null)
                ->where('size_id', $line['size_id'] ?? null)
                ->lockForUpdate()
                ->first();

            if (! $variant) {
                throw new \RuntimeException("No existe una variante de \"{$product->name}\" con esa talla/color.");
            }

            // Producto con prendas físicas pero sin prenda elegida: se toma la
            // disponible más antigua (FIFO) para dejar registro de cuál se vendió.
            if ($product->allow_rental) {
                $physical = PhysicalProduct::where('product_variant_id', $variant->id)
                    ->where('status', 'available')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();
            }
        }

        if ($variant->stock < $qty) {
            throw new \RuntimeException("Stock insuficiente de \"{$product->name}\" (disponible: {$variant->stock}, pedido: {$qty}).");
        }

        $variant->decrement('stock', $qty);

        $lineGross = $this->lineGross([...$line, 'quantity' => $qty]);
        $lineNet = round($lineGross / (1 + self::TAX_RATE), 2);
        $unitNet = round($lineNet / $qty, 2);

        // Costo exacto: el de la prenda física si la hay; si no, el promedio
        // ponderado de la variante (ver PurchaseService).
        $unitCost = round((float) ($physical?->purchase_cost ?? $variant->average_cost), 2);

        if ($physical) {
            $physical->update([
                'status' => 'sold',
                'sold_at' => now(),
                'final_sale_price' => $unitNet,
                // Vendida: si estaba separada, la separación ya no aplica.
                ...ReservationService::CLEARED,
            ]);
        }

        $physicalProductId = $physical?->id;

        $detail = $sale->details()->create([
            'product_variant_id' => $variant->id,
            'physical_product_id' => $physicalProductId,
            'quantity' => $qty,
            'unit_cost' => $unitCost,
            'unit_price' => $unitNet,
            'subtotal' => $lineNet,
            // Monto cobrado con IGV: el comprobante electrónico lo usa tal cual (ver BillingService::lines).
            'line_total' => $lineGross,
        ]);

        InventoryMovement::create([
            'product_variant_id' => $variant->id,
            'physical_product_id' => $physicalProductId,
            'user_id' => Auth::id(),
            'type' => InventoryMovement::TYPE_OUT,
            'concept' => InventoryMovement::CONCEPT_SALE,
            'quantity' => $qty,
            'unit_cost' => $unitCost,
            'total_cost' => round($unitCost * $qty, 2),
            'reference_id' => $detail->id,
            'reference_type' => SaleDetail::class,
        ]);

        return $detail;
    }

    protected function generateCode(): string
    {
        $today = now()->format('ymd');
        $sequence = Sale::whereDate('created_at', now())->count() + 1;

        return "V-{$today}-".str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
