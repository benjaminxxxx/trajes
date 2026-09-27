<?php

namespace App\Services\Purchasing;

use App\Models\InventoryMovement;
use App\Models\PhysicalProduct;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Único punto de escritura para compras: crea la cabecera, resuelve/actualiza
 * las variantes, genera productos físicos (si el producto es de alquiler) y
 * registra el movimiento de inventario correspondiente. Nadie más debe tocar
 * `stocks` / `product_variants.stock` fuera de aquí (ver StockService en el
 * módulo de ventas para el mismo patrón en salidas).
 *
 * Cada línea del detalle llega como:
 * [
 *   'product_id'   => int,
 *   'size_id'      => ?int,
 *   'color_id'     => ?int,
 *   'quantity'     => int,
 *   'unit_price'   => float,
 *   'discount_percent' => float,
 * ]
 */
class PurchaseService
{
    public function register(array $header, array $lines): Purchase
    {
        return DB::transaction(function () use ($header, $lines) {
            $subtotal = 0;

            foreach ($lines as $line) {
                $qty = (int) $line['quantity'];
                $unitPrice = (float) $line['unit_price'];
                $discount = (float) ($line['discount_percent'] ?? 0);
                $lineSubtotal = round($qty * $unitPrice * (1 - $discount / 100), 2);
                $subtotal += $lineSubtotal;
            }

            $tax = round($subtotal * 0.18, 2);
            $total = round($subtotal + $tax, 2);

            $purchase = Purchase::create([
                'supplier_id' => $header['supplier_id'] ?? null,
                'user_id' => $header['user_id'] ?? Auth::id(),
                'document_type' => $header['document_type'],
                'document_number' => $header['document_number'] ?? null,
                'has_invoice_pending' => $header['has_invoice_pending'] ?? false,
                'issue_date' => $header['issue_date'],
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total,
                'status' => 'completed',
                'notes' => $header['notes'] ?? null,
            ]);

            foreach ($lines as $line) {
                $this->registerLine($purchase, $line);
            }

            return $purchase->load('details.variant', 'details.physicalProducts');
        });
    }

    protected function registerLine(Purchase $purchase, array $line): PurchaseDetail
    {
        $qty = (int) $line['quantity'];
        $unitPrice = (float) $line['unit_price'];
        $discount = (float) ($line['discount_percent'] ?? 0);
        $lineSubtotal = round($qty * $unitPrice * (1 - $discount / 100), 2);

        /** @var Product $product */
        $product = Product::findOrFail($line['product_id']);

        // Bloqueo pesimista sobre la variante para evitar condiciones de
        // carrera si dos compras del mismo producto entran a la vez.
        $variant = ProductVariant::where('product_id', $product->id)
            ->where('color_id', $line['color_id'] ?? null)
            ->where('size_id', $line['size_id'] ?? null)
            ->lockForUpdate()
            ->first();

        if (! $variant) {
            $variant = ProductVariant::create([
                'product_id' => $product->id,
                'color_id' => $line['color_id'] ?? null,
                'size_id' => $line['size_id'] ?? null,
                'sale_price' => $product->price,
                'rental_price' => null,
                'stock' => 0,
            ]);
        }

        $variant->increment('stock', $qty);

        $detail = $purchase->details()->create([
            'product_variant_id' => $variant->id,
            'quantity' => $qty,
            'unit_price' => $unitPrice,
            'discount_percent' => $discount,
            'subtotal' => $lineSubtotal,
        ]);

        // Si el producto se maneja para alquiler, cada unidad se rastrea
        // individualmente como producto físico (código, estado, etc.).
        if ($product->allow_rental) {
            for ($i = 0; $i < $qty; $i++) {
                PhysicalProduct::create([
                    'code' => $this->generatePhysicalCode($variant),
                    'product_variant_id' => $variant->id,
                    'purchase_detail_id' => $detail->id,
                    'purchase_cost' => $unitPrice,
                    'suggested_sale_price' => $variant->sale_price,
                    'status' => 'available',
                ]);
            }
        }

        InventoryMovement::create([
            'product_variant_id' => $variant->id,
            'user_id' => Auth::id(),
            'type' => InventoryMovement::TYPE_IN,
            'concept' => InventoryMovement::CONCEPT_PURCHASE,
            'quantity' => $qty,
            'unit_cost' => $unitPrice,
            'total_cost' => $lineSubtotal,
            'reference_id' => $detail->id,
            'reference_type' => PurchaseDetail::class,
        ]);

        return $detail;
    }

    protected function generatePhysicalCode(ProductVariant $variant): string
    {
        $base = strtoupper($variant->product->code ?? 'PRD');
        $count = PhysicalProduct::where('product_variant_id', $variant->id)->count() + 1;

        return sprintf('%s-%s-%05d', $base, $variant->id, $count);
    }
}