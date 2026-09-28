<?php

namespace App\Services\Core;

use App\Models\ItemMaintenance;
use App\Models\OperationalExpense;
use App\Models\PhysicalProduct;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\Rental;
use App\Models\Sale;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Métricas del dashboard. Nunca cuenta operaciones anuladas: ventas en
 * estado `completed` y alquileres que no estén `cancelled`. Los montos de
 * venta son CON IGV (lo cobrado); el margen es sin IGV contra el costo.
 */
class DashboardService
{
    /**
     * Totales de un rango de fechas (un día, un mes...).
     *
     * Ganancia del periodo = margen de ventas + alquileres + cobros por daños
     *                        − mantenimiento − gastos operativos − pérdidas por bajas.
     *
     * Las compras NO restan: su costo entra cuando la prenda se vende (el margen
     * ya lo descuenta) o cuando se pierde/da de baja (pérdidas por bajas). Se
     * informan aparte como flujo de caja (`purchases`).
     *
     * @return array{sales_count: int, sales_total: float, sales_margin: float, rentals_count: int, rentals_total: float, income: float, expenses: float, damage_charges: float, maintenance_cost: float, disposal_losses: float, purchases: float, profit: float}
     */
    public function periodStats(CarbonInterface $from, CarbonInterface $to): array
    {
        $sales = Sale::query()
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereBetween('sale_date', [$from, $to])
            ->selectRaw('COUNT(*) as sales_count, COALESCE(SUM(total), 0) as sales_total')
            ->first();

        $margin = (float) $this->completedSaleDetails()
            ->whereBetween('sales.sale_date', [$from, $to])
            ->sum(DB::raw('sale_details.subtotal - sale_details.unit_cost * sale_details.quantity'));

        $rentals = Rental::query()
            ->where('status', '!=', Rental::STATUS_CANCELLED)
            ->whereBetween('pickup_date', [$from, $to])
            ->selectRaw('COUNT(*) as rentals_count, COALESCE(SUM(rental_total), 0) as rentals_total')
            ->first();

        $expenses = (float) OperationalExpense::whereBetween('expense_date', [$from, $to])->sum('amount');

        // Cobrado a clientes por daños o pérdidas al recepcionar alquileres.
        $damageCharges = (float) Rental::where('status', Rental::STATUS_RETURNED)
            ->whereBetween('actual_return_date', [$from, $to])
            ->sum('penalty_total');

        $maintenanceCost = (float) ItemMaintenance::whereBetween('maintenance_date', [$from->toDateString(), $to->toDateString()])
            ->sum('cost');

        // Costo de las prendas que salieron del inventario sin venderse.
        $disposalLosses = (float) PhysicalProduct::withTrashed()
            ->whereIn('status', PhysicalProduct::DISPOSED_STATUSES)
            ->whereBetween('disposed_at', [$from, $to])
            ->sum('purchase_cost');

        $purchases = (float) Purchase::where('status', 'completed')
            ->whereBetween('issue_date', [$from->toDateString(), $to->toDateString()])
            ->sum('total');

        $rentalsTotal = (float) $rentals->rentals_total;

        return [
            'sales_count' => (int) $sales->sales_count,
            'sales_total' => round((float) $sales->sales_total, 2),
            'sales_margin' => round($margin, 2),
            'rentals_count' => (int) $rentals->rentals_count,
            'rentals_total' => round($rentalsTotal, 2),
            'income' => round((float) $sales->sales_total + $rentalsTotal, 2),
            'expenses' => round($expenses, 2),
            'damage_charges' => round($damageCharges, 2),
            'maintenance_cost' => round($maintenanceCost, 2),
            'disposal_losses' => round($disposalLosses, 2),
            'purchases' => round($purchases, 2),
            'profit' => round($margin + $rentalsTotal + $damageCharges - $maintenanceCost - $expenses - $disposalLosses, 2),
        ];
    }

    /**
     * Montos por mes del año para el gráfico.
     *
     * @return array{sales: array<int, float>, rentals: array<int, float>, expenses: array<int, float>}
     */
    public function yearSeries(int $year): array
    {
        $series = [
            'sales' => array_fill(1, 12, 0.0),
            'rentals' => array_fill(1, 12, 0.0),
            'expenses' => array_fill(1, 12, 0.0),
        ];

        Sale::where('status', Sale::STATUS_COMPLETED)->whereYear('sale_date', $year)
            ->get(['sale_date', 'total'])
            ->each(function (Sale $sale) use (&$series) {
                $series['sales'][$sale->sale_date->month] += (float) $sale->total;
            });

        Rental::where('status', '!=', Rental::STATUS_CANCELLED)->whereYear('pickup_date', $year)
            ->get(['pickup_date', 'rental_total'])
            ->each(function (Rental $rental) use (&$series) {
                $series['rentals'][$rental->pickup_date->month] += (float) $rental->rental_total;
            });

        OperationalExpense::whereYear('expense_date', $year)
            ->get(['expense_date', 'amount'])
            ->each(function (OperationalExpense $expense) use (&$series) {
                $series['expenses'][$expense->expense_date->month] += (float) $expense->amount;
            });

        return array_map(fn (array $months) => array_map(fn ($value) => round($value, 2), $months), $series);
    }

    /**
     * Variantes activas con pocas unidades disponibles: prendas físicas
     * disponibles si el producto se alquila, stock si solo se vende.
     *
     * @return Collection<int, array{product: string, code: string, variant: string, available: int, rentable: bool}>
     */
    public function lowStock(int $threshold = 2, int $limit = 8): Collection
    {
        return ProductVariant::query()
            ->where('is_active', true)
            ->whereHas('product', fn ($q) => $q->where('is_active', true))
            ->with(['product:id,name,code,allow_rental', 'size:id,name', 'color:id,name'])
            ->withCount(['physicalProducts as available_units' => fn ($q) => $q->where('status', 'available')])
            ->get()
            ->map(fn (ProductVariant $variant) => [
                'product' => $variant->product->name,
                'code' => $variant->product->code,
                'variant' => $variant->label(),
                'available' => $variant->product->allow_rental ? (int) $variant->available_units : (int) $variant->stock,
                'rentable' => (bool) $variant->product->allow_rental,
            ])
            ->filter(fn (array $row) => $row['available'] <= $threshold)
            ->sortBy('available')
            ->take($limit)
            ->values();
    }

    /**
     * @return Collection<int, object{name: string, code: string, units: int, amount: float}>
     */
    public function topSold(CarbonInterface $from, CarbonInterface $to, int $limit = 5): Collection
    {
        return $this->completedSaleDetails()
            ->join('product_variants', 'product_variants.id', '=', 'sale_details.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->whereBetween('sales.sale_date', [$from, $to])
            ->groupBy('products.id', 'products.name', 'products.code')
            ->selectRaw('products.name, products.code, SUM(sale_details.quantity) as units, SUM(sale_details.subtotal) as amount')
            ->orderByDesc('units')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, object{name: string, code: string, units: int, amount: float}>
     */
    public function topRented(CarbonInterface $from, CarbonInterface $to, int $limit = 5): Collection
    {
        return DB::table('rental_details')
            ->join('rentals', 'rentals.id', '=', 'rental_details.rental_id')
            ->join('physical_products', 'physical_products.id', '=', 'rental_details.physical_product_id')
            ->join('product_variants', 'product_variants.id', '=', 'physical_products.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->whereNull('rentals.deleted_at')
            ->where('rentals.status', '!=', Rental::STATUS_CANCELLED)
            ->whereBetween('rentals.pickup_date', [$from, $to])
            ->groupBy('products.id', 'products.name', 'products.code')
            ->selectRaw('products.name, products.code, COUNT(*) as units, SUM(rental_details.price) as amount')
            ->orderByDesc('units')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array{out: int, overdue: int}
     */
    public function rentalsOut(): array
    {
        $out = Rental::where('status', Rental::STATUS_OUT);

        return [
            'out' => (clone $out)->count(),
            'overdue' => (clone $out)->where('expected_return_date', '<', now())->count(),
        ];
    }

    protected function completedSaleDetails(): Builder
    {
        return DB::table('sale_details')
            ->join('sales', 'sales.id', '=', 'sale_details.sale_id')
            ->whereNull('sales.deleted_at')
            ->where('sales.status', Sale::STATUS_COMPLETED);
    }
}
