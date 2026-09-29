<?php

namespace App\Services\Inventory;

use App\Models\PhysicalProduct;
use App\Models\Product;
use App\Models\Rental;
use App\Models\Sale;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Consultas del Seguimiento de prendas: historia completa de una prenda
 * física y estadísticas anuales de un producto. Nunca cuenta ventas ni
 * alquileres anulados en los totales.
 */
class ItemTrackingService
{
    /**
     * @return array{products: Collection<int, Product>, units: Collection<int, PhysicalProduct>}
     */
    public function search(string $term, int $limit = 8): array
    {
        $term = trim($term);

        if (mb_strlen($term) < 2) {
            return ['products' => collect(), 'units' => collect()];
        }

        $like = "%{$term}%";

        return [
            'products' => Product::with('category:id,name')
                ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('code', 'like', $like))
                ->orderBy('name')
                ->limit($limit)
                ->get(['id', 'name', 'code', 'category_id', 'allow_rental']),
            'units' => PhysicalProduct::withTrashed()
                ->with(['variant.product:id,name', 'variant.size:id,name', 'variant.color:id,name'])
                ->where('code', 'like', $like)
                ->orderBy('code')
                ->limit($limit)
                ->get(),
        ];
    }

    /**
     * Historia completa de una prenda física.
     *
     * @return array<string, mixed>
     */
    public function unitHistory(PhysicalProduct $unit): array
    {
        $unit->loadMissing([
            'variant.product.category:id,name',
            'variant.size:id,name',
            'variant.color:id,name',
            'purchaseDetail.purchase.supplier:id,display_name',
            'reservedClient:id,display_name',
        ]);

        $rentals = $unit->rentalDetails()
            ->with(['rental' => fn ($q) => $q->withTrashed()->with('client:id,display_name')])
            ->get()
            ->sortByDesc(fn ($detail) => $detail->rental?->pickup_date)
            ->values();

        $activeRentals = $rentals->filter(fn ($detail) => $detail->rental && ! $detail->rental->isCancelled());

        $saleDetail = $unit->saleDetails()
            ->with(['sale' => fn ($q) => $q->withTrashed()->with('client:id,display_name')])
            ->get()
            ->first(fn ($detail) => $detail->sale && ! $detail->sale->isCancelled());

        $maintenances = $unit->maintenances()
            ->orderByDesc('maintenance_date')
            ->orderByDesc('id')
            ->get();

        $purchase = $unit->purchaseDetail?->purchase;
        $cost = (float) $unit->purchase_cost;
        $rentalIncome = (float) $activeRentals->sum('price');
        // Cobros al cliente por daño o pérdida al recepcionar: son ingreso de la prenda.
        $damageIncome = (float) $activeRentals->sum('damage_fee');
        $saleIncome = (float) ($saleDetail?->subtotal ?? 0);
        $maintenanceCost = (float) $maintenances->sum('cost');

        $isDisposed = $unit->isDisposed();
        $disposedAt = $isDisposed ? CarbonImmutable::parse($unit->disposed_at ?? $unit->updated_at) : null;
        $since = CarbonImmutable::parse($purchase?->issue_date ?? $unit->created_at)->startOfDay();
        $until = match (true) {
            $saleDetail !== null => CarbonImmutable::parse($saleDetail->sale->sale_date),
            $isDisposed => $disposedAt,
            default => CarbonImmutable::now(),
        };

        return [
            'unit' => $unit,
            'purchase' => $purchase,
            'cost' => $cost,
            'days_in_store' => (int) $since->diffInDays($until->startOfDay()),
            'since' => $since,
            'until' => $until,
            'rentals' => $rentals,
            'rentals_count' => $activeRentals->count(),
            'rental_income' => $rentalIncome,
            'damage_income' => $damageIncome,
            'sale' => $saleDetail,
            'sale_income' => $saleIncome,
            'maintenances' => $maintenances,
            'maintenance_cost' => $maintenanceCost,
            'is_disposed' => $isDisposed,
            'disposed_at' => $disposedAt,
            // Lo que generó la prenda (sin IGV en venta) menos lo que costó comprarla y mantenerla.
            'profit' => round($rentalIncome + $damageIncome + $saleIncome - $cost - $maintenanceCost, 2),
        ];
    }

    /**
     * Estadísticas de un producto en un año: por mes, por talla y por color.
     *
     * @return array<string, mixed>
     */
    public function productYearStats(Product $product, int $year): array
    {
        $sold = $this->soldQuery($product)->whereYear('sales.sale_date', $year)
            ->get(['sales.sale_date as date', 'sale_details.quantity as units', 'sale_details.subtotal as amount',
                'sale_details.unit_cost as unit_cost', 'sizes.name as size', 'colors.name as color']);

        $rented = $product->allow_rental
            ? $this->rentedQuery($product)->whereYear('rentals.pickup_date', $year)
                ->get(['rentals.pickup_date as date', DB::raw('1 as units'), 'rental_details.price as amount',
                    'sizes.name as size', 'colors.name as color'])
            : collect();

        $months = [
            'sold' => array_fill(1, 12, 0),
            'rented' => array_fill(1, 12, 0),
        ];

        foreach ($sold as $row) {
            $months['sold'][CarbonImmutable::parse($row->date)->month] += (int) $row->units;
        }

        foreach ($rented as $row) {
            $months['rented'][CarbonImmutable::parse($row->date)->month]++;
        }

        return [
            'months' => $months,
            'by_size' => $this->groupMovements($sold, $rented, 'size', 'Talla única'),
            'by_color' => $this->groupMovements($sold, $rented, 'color', 'Sin color'),
            'units_sold' => (int) $sold->sum('units'),
            'sales_amount' => round((float) $sold->sum('amount'), 2),
            'sales_margin' => round((float) $sold->sum(fn ($row) => (float) $row->amount - (float) $row->unit_cost * (int) $row->units), 2),
            'times_rented' => $rented->count(),
            'rental_amount' => round((float) $rented->sum('amount'), 2),
        ];
    }

    /**
     * Disponibilidad actual por variante (y prendas físicas si es alquilable).
     *
     * @return Collection<int, array{label: string, stock: int, available_units: int|null}>
     */
    public function currentStock(Product $product): Collection
    {
        return $product->variants()
            ->with(['size:id,name,sort_order', 'color:id,name,hex_code'])
            ->withCount(['physicalProducts as available_units' => fn ($q) => $q->where('status', 'available')])
            ->get()
            ->sortBy([fn ($v) => $v->size?->sort_order ?? 0, fn ($v) => $v->color?->name ?? ''])
            ->map(fn ($variant) => [
                'label' => $variant->label(),
                'hex' => $variant->color?->hex_code,
                'stock' => (int) $variant->stock,
                'available_units' => $product->allow_rental ? (int) $variant->available_units : null,
            ])
            ->values();
    }

    /**
     * Prendas físicas del producto con su estado y veces alquiladas.
     *
     * @return Collection<int, PhysicalProduct>
     */
    public function productUnits(Product $product): Collection
    {
        return PhysicalProduct::withTrashed()
            ->whereIn('product_variant_id', $product->variants()->pluck('id'))
            ->with(['variant.size:id,name', 'variant.color:id,name'])
            ->withCount(['rentalDetails as rentals_count' => fn ($q) => $q->whereHas(
                'rental', fn ($r) => $r->where('status', '!=', Rental::STATUS_CANCELLED)
            )])
            ->orderBy('code')
            ->get();
    }

    /**
     * Años con movimiento del producto, del más reciente al más antiguo (incluye el actual).
     *
     * @return array<int, int>
     */
    public function years(Product $product): array
    {
        $dates = array_filter([
            $this->soldQuery($product)->min('sales.sale_date'),
            $product->allow_rental ? $this->rentedQuery($product)->min('rentals.pickup_date') : null,
        ]);

        $first = $dates ? min(array_map(fn ($date) => CarbonImmutable::parse($date)->year, $dates)) : now()->year;

        return range(now()->year, min($first, now()->year));
    }

    /**
     * Une ventas y alquileres por talla o color, ordenado por movimiento total.
     *
     * @return Collection<int, array{name: string, sold: int, rented: int, total: int}>
     */
    protected function groupMovements(Collection $sold, Collection $rented, string $field, string $emptyLabel): Collection
    {
        $groups = [];

        foreach ($sold as $row) {
            $name = $row->{$field} ?? $emptyLabel;
            $groups[$name] ??= ['name' => $name, 'sold' => 0, 'rented' => 0];
            $groups[$name]['sold'] += (int) $row->units;
        }

        foreach ($rented as $row) {
            $name = $row->{$field} ?? $emptyLabel;
            $groups[$name] ??= ['name' => $name, 'sold' => 0, 'rented' => 0];
            $groups[$name]['rented']++;
        }

        return collect($groups)
            ->map(fn (array $group) => [...$group, 'total' => $group['sold'] + $group['rented']])
            ->sortByDesc('total')
            ->values();
    }

    protected function soldQuery(Product $product): Builder
    {
        return DB::table('sale_details')
            ->join('sales', 'sales.id', '=', 'sale_details.sale_id')
            ->join('product_variants', 'product_variants.id', '=', 'sale_details.product_variant_id')
            ->leftJoin('sizes', 'sizes.id', '=', 'product_variants.size_id')
            ->leftJoin('colors', 'colors.id', '=', 'product_variants.color_id')
            ->where('product_variants.product_id', $product->id)
            ->whereNull('sales.deleted_at')
            ->where('sales.status', Sale::STATUS_COMPLETED);
    }

    protected function rentedQuery(Product $product): Builder
    {
        return DB::table('rental_details')
            ->join('rentals', 'rentals.id', '=', 'rental_details.rental_id')
            ->join('physical_products', 'physical_products.id', '=', 'rental_details.physical_product_id')
            ->join('product_variants', 'product_variants.id', '=', 'physical_products.product_variant_id')
            ->leftJoin('sizes', 'sizes.id', '=', 'product_variants.size_id')
            ->leftJoin('colors', 'colors.id', '=', 'product_variants.color_id')
            ->where('product_variants.product_id', $product->id)
            ->whereNull('rentals.deleted_at')
            ->where('rentals.status', '!=', Rental::STATUS_CANCELLED);
    }
}
