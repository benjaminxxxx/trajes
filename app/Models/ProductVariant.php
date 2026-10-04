<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use AuditsChanges, BelongsToAccount, HasFactory;

    protected $fillable = [
        'product_id',
        'color_id',
        'size_id',
        'sku',
        'sale_price',
        'rental_price',
        'average_cost',
        'stock',
        'is_active',
    ];

    protected $casts = [
        'sale_price' => 'decimal:2',
        'rental_price' => 'decimal:2',
        'average_cost' => 'decimal:4',
        'stock' => 'integer',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class);
    }

    public function physicalProducts(): HasMany
    {
        return $this->hasMany(PhysicalProduct::class);
    }

    /**
     * Talla y color legibles, ej. "Talla 40 · Negro".
     */
    public function label(): string
    {
        return collect([
            $this->size ? "Talla {$this->size->name}" : null,
            $this->color?->name,
        ])->filter()->join(' · ') ?: 'Talla única';
    }

    /**
     * Incorpora unidades a un costo dado al promedio ponderado:
     * (stock actual × costo promedio + cantidad × costo nuevo) / stock total.
     * Debe llamarse ANTES de incrementar el stock.
     */
    public function blendAverageCost(int $quantity, float $unitCost): void
    {
        $currentStock = max(0, (int) $this->stock);
        $totalUnits = $currentStock + $quantity;

        if ($totalUnits <= 0) {
            return;
        }

        $this->average_cost = round(
            ($currentStock * (float) $this->average_cost + $quantity * $unitCost) / $totalUnits,
            4
        );
        $this->save();
    }

    /**
     * Retira unidades que entraron a un costo dado (corrección de compra).
     * Debe llamarse ANTES de decrementar el stock.
     */
    public function removeFromAverageCost(int $quantity, float $unitCost): void
    {
        $remainingUnits = (int) $this->stock - $quantity;

        if ($remainingUnits <= 0) {
            return;
        }

        $this->average_cost = round(max(0,
            ((int) $this->stock * (float) $this->average_cost - $quantity * $unitCost) / $remainingUnits
        ), 4);
        $this->save();
    }

    /**
     * El stock y el costo promedio cambian solos con cada compra y venta (ver inventory_movements).
     */
    public function auditExcluded(): array
    {
        return ['stock', 'average_cost'];
    }
}
