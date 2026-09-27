<?php

namespace App\Services\Sale;

use App\Models\CashMovement;
use App\Models\InventoryMovement;
use App\Models\PhysicalProduct;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleDetail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Único punto de escritura para ventas: descuenta stock de la variante,
 * consume una unidad física (FIFO) si el producto se rastrea individualmente,
 * registra el movimiento de inventario y el ingreso en caja. Simétrico a
 * PurchaseService pero en sentido inverso (salida en vez de entrada).
 */
class SaleService
{
    public function register(array $header, array $lines): Sale
    {
        return DB::transaction(function () use ($header, $lines) {
            $subtotal = 0;

            foreach ($lines as $line) {
                $subtotal += $this->lineSubtotal($line);
            }

            $tax = round($subtotal * 0.18, 2);
            $total = round($subtotal + $tax, 2);

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
                'status' => 'completed',
                'notes' => $header['notes'] ?? null,
            ]);

            foreach ($lines as $line) {
                $this->registerLine($sale, $line);
            }

            CashMovement::create([
                'user_id' => Auth::id(),
                'type' => CashMovement::TYPE_INCOME,
                'origin' => CashMovement::ORIGIN_SALE_PAYMENT,
                'amount' => $total,
                'payment_method' => $header['payment_method'] ?? 'cash',
                'reference_id' => $sale->id,
                'reference_type' => Sale::class,
                'description' => "Venta {$sale->code}",
            ]);

            return $sale->load('details.variant', 'details.physicalProduct');
        });
    }

    protected function lineSubtotal(array $line): float
    {
        $qty = (int) $line['quantity'];
        $unitPrice = (float) $line['unit_price'];
        $discount = (float) ($line['discount_percent'] ?? 0);

        return round($qty * $unitPrice * (1 - $discount / 100), 2);
    }

    protected function registerLine(Sale $sale, array $line): SaleDetail
    {
        $qty = (int) $line['quantity'];
        $unitPrice = (float) $line['unit_price'];
        $lineSubtotal = $this->lineSubtotal($line);

        /** @var Product $product */
        $product = Product::findOrFail($line['product_id']);

        $variant = ProductVariant::where('product_id', $product->id)
            ->where('color_id', $line['color_id'] ?? null)
            ->where('size_id', $line['size_id'] ?? null)
            ->lockForUpdate()
            ->first();

        if (! $variant) {
            throw new \RuntimeException("No existe una variante de \"{$product->name}\" con esa talla/color.");
        }

        if ($variant->stock < $qty) {
            throw new \RuntimeException("Stock insuficiente de \"{$product->name}\" (disponible: {$variant->stock}, pedido: {$qty}).");
        }

        $variant->decrement('stock', $qty);

        // Si el producto se rastrea por unidad física (se usa también para
        // alquiler), consumimos la unidad disponible más antigua (FIFO) para
        // dejar registro de cuál se vendió. Si no, la venta solo afecta el
        // stock agregado de la variante.
        $physicalProductId = null;
        $unitCost = $variant->sale_price ?? 0;

        if ($product->allow_rental) {
            $physical = PhysicalProduct::where('product_variant_id', $variant->id)
                ->where('status', 'available')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($physical) {
                $physical->update([
                    'status' => 'sold',
                    'sold_at' => now(),
                    'final_sale_price' => $unitPrice,
                ]);
                $physicalProductId = $physical->id;
                $unitCost = $physical->purchase_cost ?? $unitCost;
            }
        }

        $detail = $sale->details()->create([
            'product_variant_id' => $variant->id,
            'physical_product_id' => $physicalProductId,
            'quantity' => $qty,
            'unit_cost' => $unitCost,
            'unit_price' => $unitPrice,
            'subtotal' => $lineSubtotal,
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