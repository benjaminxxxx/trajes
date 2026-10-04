<?php

namespace App\Services\Assets;

use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\CashMovement;
use App\Models\Sale;
use App\Models\SaleAssetDelivery;
use App\Services\Finance\CashService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Único punto de escritura del stock de bienes: registro, ingresos (con costo
 * promedio ponderado), bajas, cambios de valor y entregas de consumibles con
 * las ventas. Cada operación deja su fila en asset_movements, y las compras
 * pagadas desde caja generan su egreso.
 */
class AssetService
{
    /**
     * @param  array{name: string, type: string, quantity: int, unit_cost: float, current_unit_value?: float|null, is_consumable?: bool, delivers_with_sale?: bool, notes?: string|null}  $data
     * @param  array<int, int>  $categoryIds
     */
    public function create(array $data, array $categoryIds = [], bool $paidFromCash = false): Asset
    {
        return DB::transaction(function () use ($data, $categoryIds, $paidFromCash) {
            $asset = Asset::create([
                ...$data,
                'current_unit_value' => $data['current_unit_value'] ?? $data['unit_cost'],
                'status' => Asset::STATUS_ACTIVE,
            ]);

            $asset->categories()->sync($categoryIds);
            $this->log($asset, 'initial', $asset->quantity, (float) $asset->unit_cost, 'Registro inicial');

            if ($paidFromCash) {
                $this->payFromCash($asset, $asset->quantity, (float) $asset->unit_cost);
            }

            return $asset;
        });
    }

    /**
     * Compra de más unidades: suma stock y promedia costo de compra y valor actual.
     */
    public function addStock(Asset $asset, int $quantity, float $unitCost, ?string $note = null, bool $paidFromCash = false): Asset
    {
        return DB::transaction(function () use ($asset, $quantity, $unitCost, $note, $paidFromCash) {
            $asset = Asset::whereKey($asset->id)->lockForUpdate()->firstOrFail();
            $newQuantity = $asset->quantity + $quantity;

            $asset->update([
                'quantity' => $newQuantity,
                'unit_cost' => $this->weighted($asset->quantity, (float) $asset->unit_cost, $quantity, $unitCost, $newQuantity),
                'current_unit_value' => $this->weighted($asset->quantity, (float) $asset->current_unit_value, $quantity, $unitCost, $newQuantity),
                'status' => Asset::STATUS_ACTIVE,
            ]);

            $this->log($asset, 'in', $quantity, $unitCost, $note);

            if ($paidFromCash) {
                $this->payFromCash($asset, $quantity, $unitCost);
            }

            return $asset;
        });
    }

    /**
     * Baja de unidades (malogradas, perdidas). Si no queda ninguna, el bien pasa a "dado de baja".
     */
    public function discharge(Asset $asset, int $quantity, string $reason): Asset
    {
        return DB::transaction(function () use ($asset, $quantity, $reason) {
            $asset = Asset::whereKey($asset->id)->lockForUpdate()->firstOrFail();

            if ($quantity < 1 || $quantity > $asset->quantity) {
                throw new \RuntimeException("Solo hay {$asset->quantity} unidad(es) de {$asset->name} para dar de baja.");
            }

            $remaining = $asset->quantity - $quantity;

            $asset->update([
                'quantity' => $remaining,
                'status' => $remaining === 0 ? Asset::STATUS_DISCHARGED : $asset->status,
            ]);

            $this->log($asset, 'discharge', -$quantity, (float) $asset->current_unit_value, $reason);

            return $asset;
        });
    }

    /**
     * Ajusta el valor actual por unidad (ej. el escritorio ya no vale 450).
     */
    public function revalue(Asset $asset, float $currentUnitValue, ?string $note = null): Asset
    {
        $previous = (float) $asset->current_unit_value;
        $asset->update(['current_unit_value' => $currentUnitValue]);

        $this->log($asset, 'revalue', 0, $currentUnitValue, trim('Antes S/ '.number_format($previous, 2).'. '.($note ?? '')));

        return $asset;
    }

    /**
     * Deja las entregas de una venta en un solo formato. Acepta el mapa
     * simple de regalos (asset_id => cantidad) y las líneas con precio.
     *
     * @param  array<int|string, int|string|array{asset_id: int, quantity: int|string, unit_price?: float|string|null}>  $deliveries
     * @return array<int, array{asset_id: int, quantity: int, unit_price: float}>
     */
    public function normalizeDeliveries(array $deliveries): array
    {
        $lines = [];

        foreach ($deliveries as $key => $value) {
            $line = is_array($value) ? $value : ['asset_id' => $key, 'quantity' => $value];
            $quantity = (int) ($line['quantity'] ?? 0);

            if ($quantity > 0) {
                $lines[] = [
                    'asset_id' => (int) $line['asset_id'],
                    'quantity' => $quantity,
                    'unit_price' => round(max(0, (float) ($line['unit_price'] ?? 0)), 2),
                ];
            }
        }

        return $lines;
    }

    /**
     * Bienes que salen con una venta: regalados (portaternos, correas...) o
     * vendidos, si el bien tiene precio de venta. El cobro va en el total de
     * la venta; aquí se descuenta el stock y se guarda costo y precio.
     *
     * @param  array<int, array{asset_id: int, quantity: int, unit_price: float}>  $deliveries  ver normalizeDeliveries()
     */
    public function deliverForSale(Sale $sale, array $deliveries): void
    {
        foreach ($deliveries as $delivery) {
            $quantity = $delivery['quantity'];
            $sold = $delivery['unit_price'] > 0;

            $asset = Asset::active()->whereKey($delivery['asset_id'])->lockForUpdate()->first();

            if (! $asset || ! ($asset->sale_price !== null || ($asset->is_consumable && $asset->delivers_with_sale))) {
                throw new \RuntimeException('El bien a entregar ya no está disponible.');
            }

            if ($sold && $asset->sale_price === null) {
                throw new \RuntimeException("{$asset->name} no tiene precio de venta: no se puede vender.");
            }

            if ($asset->quantity < $quantity) {
                throw new \RuntimeException("Stock insuficiente de {$asset->name} (disponible: {$asset->quantity}).");
            }

            $asset->decrement('quantity', $quantity);

            SaleAssetDelivery::create([
                'sale_id' => $sale->id,
                'asset_id' => $asset->id,
                'quantity' => $quantity,
                'unit_cost' => $asset->unit_cost,
                'line_total' => round($quantity * $delivery['unit_price'], 2),
            ]);

            $this->log($asset, $sold ? 'sale' : 'delivery', -$quantity, (float) $asset->unit_cost, "Venta {$sale->code}", $sale);
        }
    }

    /**
     * Venta anulada: los consumibles entregados vuelven al stock.
     */
    public function returnForSale(Sale $sale): void
    {
        foreach ($sale->assetDeliveries()->with('asset')->get() as $delivery) {
            $delivery->asset->increment('quantity', $delivery->quantity);

            $this->log($delivery->asset, 'delivery_return', $delivery->quantity, (float) $delivery->unit_cost, "Anulación venta {$sale->code}", $sale);
        }
    }

    /**
     * Compra de un bien pagada desde caja.
     */
    protected function payFromCash(Asset $asset, int $quantity, float $unitCost): void
    {
        app(CashService::class)->expense(
            CashMovement::ORIGIN_ASSET_PURCHASE,
            round($quantity * $unitCost, 2),
            "Compra de {$quantity} × {$asset->name}",
            $asset,
        );
    }

    protected function weighted(int $currentQuantity, float $currentCost, int $addedQuantity, float $addedCost, int $total): float
    {
        return $total > 0
            ? round(($currentQuantity * $currentCost + $addedQuantity * $addedCost) / $total, 4)
            : $addedCost;
    }

    protected function log(Asset $asset, string $type, int $quantity, float $unitCost, ?string $note = null, ?Sale $sale = null): void
    {
        AssetMovement::create([
            'asset_id' => $asset->id,
            'user_id' => Auth::id(),
            'type' => $type,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'reference_id' => $sale?->id,
            'reference_type' => $sale ? Sale::class : null,
            'note' => $note,
        ]);
    }
}
