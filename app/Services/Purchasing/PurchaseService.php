<?php

namespace App\Services\Purchasing;

use App\Models\CashMovement;
use App\Models\InventoryMovement;
use App\Models\PhysicalProduct;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Services\Finance\CashService;
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
    public const TAX_RATE = 0.18;

    /**
     * Guarda (o actualiza) un borrador: cabecera y líneas tal cual, sin
     * resolver variantes ni tocar stock o caja. Las líneas pueden no tener
     * talla/color todavía; se completan antes de confirmar.
     */
    public function saveDraft(array $header, array $lines, ?Purchase $draft = null): Purchase
    {
        return DB::transaction(function () use ($header, $lines, $draft) {
            $attributes = $this->headerAttributes($header)
                + $this->totals($this->linesAmount($lines), (bool) ($header['prices_include_tax'] ?? false))
                + ['status' => Purchase::STATUS_DRAFT, 'draft_items' => array_values($lines)];

            if ($draft) {
                $draft = $this->lockDraft($draft);
                $draft->update($attributes);

                return $draft;
            }

            return Purchase::create($attributes);
        });
    }

    public function deleteDraft(Purchase $draft): void
    {
        DB::transaction(fn () => $this->lockDraft($draft)->delete());
    }

    /**
     * Registra una compra confirmada: stock, costo y salida de caja. Si viene
     * de un borrador, ese mismo registro pasa a confirmado.
     */
    public function register(array $header, array $lines, ?Purchase $draft = null): Purchase
    {
        return DB::transaction(function () use ($header, $lines, $draft) {
            if ($lines === []) {
                throw new \RuntimeException('Agrega al menos un producto a la compra.');
            }

            $attributes = $this->headerAttributes($header)
                + $this->totals($this->linesAmount($lines), (bool) ($header['prices_include_tax'] ?? false))
                + ['status' => Purchase::STATUS_COMPLETED, 'draft_items' => null];

            if ($draft) {
                $purchase = $this->lockDraft($draft);
                $purchase->update($attributes);
            } else {
                $purchase = Purchase::create($attributes);
            }

            $total = (float) $purchase->total;

            foreach ($lines as $line) {
                $this->registerLine($purchase, $line);
            }

            // Lo pagado al proveedor (con IGV) sale de caja.
            app(CashService::class)->expense(
                CashMovement::ORIGIN_PURCHASE_EXPENSE,
                $total,
                trim("Compra #{$purchase->id} ".($purchase->document_number ?? '')),
                $purchase,
            );

            return $purchase->load('details.variant', 'details.physicalProducts');
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function headerAttributes(array $header): array
    {
        return [
            'supplier_id' => $header['supplier_id'] ?? null,
            'user_id' => $header['user_id'] ?? Auth::id(),
            'document_type' => $header['document_type'],
            'document_number' => $header['document_number'] ?? null,
            'has_invoice_pending' => $header['has_invoice_pending'] ?? false,
            'prices_include_tax' => (bool) ($header['prices_include_tax'] ?? false),
            'document_total' => $header['document_total'] ?? null,
            'issue_date' => $header['issue_date'],
            'notes' => $header['notes'] ?? null,
        ];
    }

    protected function lockDraft(Purchase $draft): Purchase
    {
        $draft = Purchase::whereKey($draft->id)->lockForUpdate()->firstOrFail();

        if (! $draft->isDraft()) {
            throw new \RuntimeException("La compra #{$draft->id} ya fue confirmada: corrígela desde su detalle.");
        }

        return $draft;
    }

    /**
     * Importe de una línea tal como figura en el comprobante (con o sin IGV,
     * según cómo se ingresaron los precios).
     */
    public function lineAmount(array $line): float
    {
        return round((int) $line['quantity'] * (float) $line['unit_price'] * (1 - (float) ($line['discount_percent'] ?? 0) / 100), 2);
    }

    public function linesAmount(array $lines): float
    {
        return round(array_sum(array_map(fn (array $line) => $this->lineAmount($line), $lines)), 2);
    }

    /**
     * Subtotal (base imponible), IGV y total a partir de la suma de líneas.
     * Si los precios ya incluyen IGV, el total es lo que suman las líneas y el
     * IGV se extrae; si no, se agrega encima.
     *
     * @return array{subtotal: float, tax: float, total: float}
     */
    public function totals(float $linesAmount, bool $pricesIncludeTax): array
    {
        $linesAmount = round($linesAmount, 2);

        if ($pricesIncludeTax) {
            $subtotal = round($linesAmount / (1 + self::TAX_RATE), 2);

            return ['subtotal' => $subtotal, 'tax' => round($linesAmount - $subtotal, 2), 'total' => $linesAmount];
        }

        $tax = round($linesAmount * self::TAX_RATE, 2);

        return ['subtotal' => $linesAmount, 'tax' => $tax, 'total' => round($linesAmount + $tax, 2)];
    }

    protected function registerLine(Purchase $purchase, array $line): PurchaseDetail
    {
        $qty = (int) $line['quantity'];
        $unitPrice = (float) $line['unit_price'];
        $discount = (float) ($line['discount_percent'] ?? 0);
        $lineSubtotal = $this->lineAmount($line);

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

        // Costo real por unidad (con el descuento de la línea): es el que se copia
        // a cada prenda física y el que entra al promedio ponderado. Ver unitCost().
        $unitCost = $this->unitCost($lineSubtotal, $qty, $purchase->document_type, $purchase->prices_include_tax);

        $variant->blendAverageCost($qty, $unitCost);
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
                    'purchase_cost' => round($unitCost, 2),
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
            'unit_cost' => round($unitCost, 2),
            'total_cost' => round($unitCost * $qty, 2),
            'reference_id' => $detail->id,
            'reference_type' => PurchaseDetail::class,
        ]);

        return $detail;
    }

    /**
     * Corrige (reduce) la cantidad comprada de una línea, ej. la factura decía 5
     * y se registraron 10. Nunca deja el stock en negativo:
     *
     * - Producto sin prendas físicas: se permite si la variante aún tiene stock
     *   suficiente para retirar la diferencia.
     * - Producto con prendas físicas: solo se eliminan prendas de ESTA línea que
     *   nunca se operaron (disponibles, sin alquiler, venta ni mantenimiento);
     *   si no hay suficientes, no se permite.
     */
    public function reduceLineQuantity(PurchaseDetail $detail, int $newQuantity): PurchaseDetail
    {
        return DB::transaction(function () use ($detail, $newQuantity) {
            $detail = PurchaseDetail::whereKey($detail->id)->lockForUpdate()->firstOrFail();
            $variant = ProductVariant::whereKey($detail->product_variant_id)->lockForUpdate()->firstOrFail();

            if ($newQuantity < 1) {
                throw new \RuntimeException('La cantidad debe ser al menos 1.');
            }

            if ($newQuantity >= $detail->quantity) {
                throw new \RuntimeException('Solo se puede reducir la cantidad. Para agregar unidades registra una nueva compra.');
            }

            $removed = $detail->quantity - $newQuantity;
            $unitCost = $this->unitCost((float) $detail->subtotal, $detail->quantity, $detail->purchase->document_type, $detail->purchase->prices_include_tax);
            $hasPhysicalUnits = $detail->physicalProducts()->withTrashed()->exists();

            if ($hasPhysicalUnits) {
                $removableUnits = $detail->physicalProducts()
                    ->neverOperated()
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->limit($removed)
                    ->get();

                if ($removableUnits->count() < $removed) {
                    throw new \RuntimeException(
                        "Solo {$removableUnits->count()} prenda(s) de esta línea nunca se han operado; no se pueden quitar {$removed}."
                    );
                }

                $removableUnits->each->forceDelete();
            } elseif ($variant->stock < $removed) {
                throw new \RuntimeException(
                    "El stock actual de la variante es {$variant->stock}; quitar {$removed} unidades lo dejaría en negativo."
                );
            }

            $variant->removeFromAverageCost($removed, $unitCost);
            $variant->decrement('stock', $removed);

            $detail->update([
                'quantity' => $newQuantity,
                'subtotal' => round($newQuantity * (float) $detail->unit_price * (1 - (float) $detail->discount_percent / 100), 2),
            ]);

            InventoryMovement::create([
                'product_variant_id' => $variant->id,
                'user_id' => Auth::id(),
                'type' => InventoryMovement::TYPE_OUT,
                'concept' => InventoryMovement::CONCEPT_PURCHASE_CORRECTION,
                'quantity' => $removed,
                'unit_cost' => round($unitCost, 2),
                'total_cost' => round($unitCost * $removed, 2),
                'reference_id' => $detail->id,
                'reference_type' => PurchaseDetail::class,
                'notes' => "Corrección de cantidad en compra #{$detail->purchase_id}",
            ]);

            $previousTotal = (float) $detail->purchase->total;
            $this->recalculateTotals($detail->purchase);

            // Lo que ya no se compró vuelve a caja.
            app(CashService::class)->income(
                CashMovement::ORIGIN_PURCHASE_EXPENSE,
                $previousTotal - (float) $detail->purchase->fresh()->total,
                "Reingreso por corrección de la compra #{$detail->purchase_id}",
                $detail->purchase,
            );

            return $detail;
        });
    }

    /**
     * Máximo de unidades que se pueden quitar de una línea sin romper el stock.
     */
    public function removableQuantity(PurchaseDetail $detail): int
    {
        if ($detail->physicalProducts()->withTrashed()->exists()) {
            return min($detail->quantity - 1, $detail->physicalProducts()->neverOperated()->count());
        }

        return max(0, min($detail->quantity - 1, (int) $detail->variant->stock));
    }

    /**
     * Costo unitario real de una línea de compra.
     *
     * Con factura el IGV se recupera como crédito fiscal, así que el costo es
     * el precio SIN IGV. Con boleta o nota de venta ese IGV no se deduce: es
     * parte de lo que realmente costó la prenda, así que el costo es el pago
     * total CON IGV. Esto es lo que determina el margen real de cada venta.
     *
     * `$pricesIncludeTax` indica si el importe de la línea ya trae el IGV.
     */
    public function unitCost(float $lineSubtotal, int $quantity, string $documentType, bool $pricesIncludeTax = false): float
    {
        if ($quantity <= 0) {
            return 0.0;
        }

        $net = $pricesIncludeTax ? $lineSubtotal / (1 + self::TAX_RATE) : $lineSubtotal;
        $cost = $documentType === 'factura' ? $net : $net * (1 + self::TAX_RATE);

        return round($cost / $quantity, 4);
    }

    /**
     * Actualiza la cabecera de una compra. Si cambia entre factura y otro
     * comprobante, el costo de sus unidades cambia (ver unitCost()), así que se
     * recalcula en las prendas físicas y en el costo promedio de las variantes.
     * Las ventas ya registradas conservan el costo con que se vendieron.
     */
    public function updateHeader(Purchase $purchase, array $data): Purchase
    {
        return DB::transaction(function () use ($purchase, $data) {
            $purchase = Purchase::whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            $previousType = $purchase->document_type;

            $purchase->update($data);

            $wasDeductible = $previousType === 'factura';
            $isDeductible = $purchase->document_type === 'factura';

            if ($wasDeductible !== $isDeductible) {
                foreach ($purchase->details()->with('variant')->get() as $detail) {
                    $this->recostLine($detail, $previousType, $purchase->document_type);
                }
            }

            return $purchase;
        });
    }

    protected function recostLine(PurchaseDetail $detail, string $previousType, string $newType): void
    {
        $pricesIncludeTax = (bool) $detail->purchase->prices_include_tax;
        $oldCost = $this->unitCost((float) $detail->subtotal, $detail->quantity, $previousType, $pricesIncludeTax);
        $newCost = $this->unitCost((float) $detail->subtotal, $detail->quantity, $newType, $pricesIncludeTax);

        $detail->physicalProducts()->withTrashed()->update(['purchase_cost' => round($newCost, 2)]);

        // Ajuste del promedio: las unidades de esta compra que siguen en stock
        // pasan de costar $oldCost a $newCost.
        $variant = ProductVariant::whereKey($detail->product_variant_id)->lockForUpdate()->first();
        $unitsInStock = min($detail->quantity, max(0, (int) $variant?->stock));

        if ($variant && $unitsInStock > 0) {
            $variant->update([
                'average_cost' => round(max(0,
                    (float) $variant->average_cost + ($newCost - $oldCost) * $unitsInStock / $variant->stock
                ), 4),
            ]);
        }

        $detail->movements()->where('concept', InventoryMovement::CONCEPT_PURCHASE)->update([
            'unit_cost' => round($newCost, 2),
            'total_cost' => round($newCost * $detail->quantity, 2),
        ]);
    }

    protected function recalculateTotals(Purchase $purchase): void
    {
        $purchase->update($this->totals((float) $purchase->details()->sum('subtotal'), (bool) $purchase->prices_include_tax));
    }

    protected function generatePhysicalCode(ProductVariant $variant): string
    {
        $base = strtoupper($variant->product->code ?? 'PRD');
        $count = PhysicalProduct::where('product_variant_id', $variant->id)->count() + 1;

        return sprintf('%s-%s-%05d', $base, $variant->id, $count);
    }
}
