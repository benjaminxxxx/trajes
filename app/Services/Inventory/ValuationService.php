<?php

namespace App\Services\Inventory;

use App\Models\Asset;
use App\Models\DepreciationRule;
use App\Models\PhysicalProduct;
use App\Models\ProductVariant;
use App\Models\Rental;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Valorizado de la empresa: lo que costó y lo que vale hoy todo lo que tiene
 * el negocio. Tres bloques:
 *  - Prendas de alquiler (cada prenda física en poder del negocio): su costo
 *    de compra, menos el desgaste por cantidad de alquileres (DepreciationRule).
 *  - Productos solo de venta: stock × costo promedio (no se desgastan).
 *  - Bienes (mobiliario, maniquíes, consumibles...): cantidad × valor actual.
 */
class ValuationService
{
    /**
     * Estados en que la prenda sigue siendo del negocio.
     */
    public const OWNED_STATUSES = ['available', 'rented', 'maintenance'];

    /** @var Collection<int, DepreciationRule>|null */
    protected ?Collection $rules = null;

    /**
     * % de desgaste según la regla más alta alcanzada.
     */
    public function depreciationPercent(int $rentals): float
    {
        $this->rules ??= DepreciationRule::orderByDesc('min_rentals')->get();

        return (float) ($this->rules->first(fn (DepreciationRule $rule) => $rentals >= $rule->min_rentals)?->percent ?? 0);
    }

    public function garmentsQuery(): Builder
    {
        return PhysicalProduct::query()
            ->whereIn('status', self::OWNED_STATUSES)
            ->withCount(['rentalDetails as rentals_count' => fn (Builder $query) => $query
                ->whereHas('rental', fn (Builder $rental) => $rental->where('status', '!=', Rental::STATUS_CANCELLED))]);
    }

    /**
     * Agrega a cada prenda su % de desgaste y su valor actual.
     */
    public function valueGarment(PhysicalProduct $unit): PhysicalProduct
    {
        $percent = $this->depreciationPercent((int) $unit->rentals_count);
        $unit->depreciation_percent = $percent;
        $unit->current_value = round((float) $unit->purchase_cost * (1 - $percent / 100), 2);

        return $unit;
    }

    public function saleStockQuery(): Builder
    {
        return ProductVariant::query()
            ->where('stock', '>', 0)
            ->whereHas('product', fn (Builder $query) => $query->where('allow_rental', false));
    }

    public function assetsQuery(): Builder
    {
        return Asset::active()->where('quantity', '>', 0);
    }

    /**
     * @return array{garments: array{count: int, cost: float, value: float}, stock: array{count: int, cost: float, value: float}, assets: array{count: int, cost: float, value: float}, cost: float, value: float}
     */
    public function summary(): array
    {
        $garments = ['count' => 0, 'cost' => 0.0, 'value' => 0.0];

        $this->garmentsQuery()->chunkById(500, function ($units) use (&$garments) {
            foreach ($units as $unit) {
                $this->valueGarment($unit);
                $garments['count']++;
                $garments['cost'] += (float) $unit->purchase_cost;
                $garments['value'] += $unit->current_value;
            }
        });

        $stockCost = (float) $this->saleStockQuery()->sum(DB::raw('stock * average_cost'));
        $stock = ['count' => (int) $this->saleStockQuery()->sum('stock'), 'cost' => $stockCost, 'value' => $stockCost];

        $assetTotals = [
            'count' => (int) $this->assetsQuery()->sum('quantity'),
            'cost' => (float) $this->assetsQuery()->sum(DB::raw('quantity * unit_cost')),
            'value' => (float) $this->assetsQuery()->sum(DB::raw('quantity * current_unit_value')),
        ];

        $blocks = array_map(fn (array $block) => [
            ...$block,
            'cost' => round($block['cost'], 2),
            'value' => round($block['value'], 2),
        ], ['garments' => $garments, 'stock' => $stock, 'assets' => $assetTotals]);

        return [
            ...$blocks,
            'cost' => round(array_sum(array_column($blocks, 'cost')), 2),
            'value' => round(array_sum(array_column($blocks, 'value')), 2),
        ];
    }
}
