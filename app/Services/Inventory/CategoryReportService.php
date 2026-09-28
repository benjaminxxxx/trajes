<?php

namespace App\Services\Inventory;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Consultas de actividad por categoría. Una unidad alquilada es cada prenda
 * de un alquiler no cancelado (rental_details → prenda → variante → producto);
 * una unidad vendida es la cantidad de cada detalle de una venta completada.
 */
class CategoryReportService
{
    /**
     * Tabla de doble entrada del año: alquileres y ventas (unidades) por mes.
     *
     * @return array{rentals: array<int, int>, sales: array<int, int>}
     */
    public function monthlyMatrix(int $year, ?int $categoryId = null): array
    {
        $rentals = array_fill(1, 12, 0);
        $sales = array_fill(1, 12, 0);

        $this->rentedUnitsQuery()
            ->whereYear('rentals.pickup_date', $year)
            ->when($categoryId, fn (Builder $q) => $q->where('products.category_id', $categoryId))
            ->pluck('rentals.pickup_date')
            ->each(function ($date) use (&$rentals) {
                $rentals[CarbonImmutable::parse($date)->month]++;
            });

        $this->soldUnitsQuery()
            ->whereYear('sales.sale_date', $year)
            ->when($categoryId, fn (Builder $q) => $q->where('products.category_id', $categoryId))
            ->get(['sales.sale_date', 'sale_details.quantity'])
            ->each(function ($row) use (&$sales) {
                $sales[CarbonImmutable::parse($row->sale_date)->month] += (int) $row->quantity;
            });

        return ['rentals' => $rentals, 'sales' => $sales];
    }

    /**
     * Unidades alquiladas y vendidas por categoría; históricas si no se indica año.
     *
     * @return array{rentals: array<int, int>, sales: array<int, int>}
     */
    public function unitsByCategory(?int $year = null): array
    {
        return [
            'rentals' => $this->rentedUnitsQuery()
                ->when($year, fn (Builder $q) => $q->whereYear('rentals.pickup_date', $year))
                ->selectRaw('products.category_id, COUNT(*) as units')
                ->groupBy('products.category_id')
                ->pluck('units', 'category_id')
                ->map(fn ($units) => (int) $units)
                ->all(),
            'sales' => $this->soldUnitsQuery()
                ->when($year, fn (Builder $q) => $q->whereYear('sales.sale_date', $year))
                ->selectRaw('products.category_id, SUM(sale_details.quantity) as units')
                ->groupBy('products.category_id')
                ->pluck('units', 'category_id')
                ->map(fn ($units) => (int) $units)
                ->all(),
        ];
    }

    /**
     * Tabla de doble entrada del año para un tipo de operación: categorías × mes.
     * La métrica puede ser 'units' (unidades) o 'amount' (importe en soles).
     *
     * @param  'rentals'|'sales'  $type
     * @param  'units'|'amount'  $metric
     * @return array<string, array{months: array<int, float>, total: float}>
     */
    public function categoryMonthMatrix(string $type, int $year, string $metric = 'units'): array
    {
        [$query, $dateColumn, $unitsColumn, $amountColumn] = $type === 'rentals'
            ? [$this->rentedUnitsQuery(), 'rentals.pickup_date', null, 'rental_details.price']
            : [$this->soldUnitsQuery(), 'sales.sale_date', 'sale_details.quantity', 'sale_details.subtotal'];

        $rows = $query
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->whereYear($dateColumn, $year)
            ->get([
                'categories.name as category',
                "{$dateColumn} as date",
                DB::raw(($unitsColumn ?? '1').' as units'),
                "{$amountColumn} as amount",
            ]);

        $matrix = [];

        foreach ($rows as $row) {
            $category = $row->category ?? 'Sin categoría';
            $matrix[$category] ??= ['months' => array_fill(1, 12, 0.0), 'total' => 0.0];

            $value = $metric === 'amount' ? (float) $row->amount : (float) $row->units;
            $matrix[$category]['months'][CarbonImmutable::parse($row->date)->month] += $value;
            $matrix[$category]['total'] += $value;
        }

        uasort($matrix, fn (array $a, array $b) => $b['total'] <=> $a['total']);

        return $matrix;
    }

    /**
     * Años con actividad, del más reciente al más antiguo (siempre incluye el actual).
     *
     * @return array<int, int>
     */
    public function years(): array
    {
        $dates = array_filter([
            $this->rentedUnitsQuery()->min('rentals.pickup_date'),
            $this->soldUnitsQuery()->min('sales.sale_date'),
        ]);

        $bottom = $dates
            ? min(array_map(fn ($date) => CarbonImmutable::parse($date)->year, $dates))
            : now()->year;

        return range(now()->year, min($bottom, now()->year));
    }

    protected function rentedUnitsQuery(): Builder
    {
        return DB::table('rental_details')
            ->join('rentals', 'rentals.id', '=', 'rental_details.rental_id')
            ->join('physical_products', 'physical_products.id', '=', 'rental_details.physical_product_id')
            ->join('product_variants', 'product_variants.id', '=', 'physical_products.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->whereNull('rentals.deleted_at')
            ->where('rentals.status', '!=', 'cancelled');
    }

    protected function soldUnitsQuery(): Builder
    {
        return DB::table('sale_details')
            ->join('sales', 'sales.id', '=', 'sale_details.sale_id')
            ->join('product_variants', 'product_variants.id', '=', 'sale_details.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->whereNull('sales.deleted_at')
            ->where('sales.status', 'completed');
    }
}
