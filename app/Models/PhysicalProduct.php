<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class PhysicalProduct extends Model
{
    use BelongsToAccount, HasFactory, SoftDeletes;

    protected $table = 'physical_products';

    public const STATUS_LABELS = [
        'available' => 'Disponible',
        'rented' => 'Alquilada',
        'sold' => 'Vendida',
        'maintenance' => 'En mantenimiento',
        'lost' => 'Perdida',
        'stolen' => 'Robada',
        'not_delivered' => 'No entregada',
        'discharged' => 'Dada de baja',
    ];

    public const STATUS_COLORS = [
        'available' => 'emerald',
        'rented' => 'sky',
        'sold' => 'violet',
        'maintenance' => 'amber',
        'lost' => 'red',
        'stolen' => 'red',
        'not_delivered' => 'red',
        'discharged' => 'zinc',
    ];

    protected $fillable = [
        'code',
        'product_variant_id',
        'purchase_detail_id',
        'purchase_cost',
        'suggested_sale_price',
        'final_sale_price',
        'rental_price',
        'status',
        'status_note',
        'sold_at',
        'disposed_at',
        'reserved_client_id',
        'reserved_until',
        'reservation_note',
        'reserved_by',
        'reserved_at',
    ];

    protected $casts = [
        'purchase_cost' => 'decimal:2',
        'suggested_sale_price' => 'decimal:2',
        'final_sale_price' => 'decimal:2',
        'rental_price' => 'decimal:2',
        'sold_at' => 'date',
        'disposed_at' => 'datetime',
        'reserved_until' => 'date',
        'reserved_at' => 'datetime',
    ];

    /**
     * Estados en que la prenda sale del inventario sin venderse.
     */
    public const DISPOSED_STATUSES = ['discharged', 'lost', 'stolen'];

    /**
     * Registra la fecha exacta de baja al pasar a un estado de baja (y la
     * limpia si la prenda se recupera), sin depender de quién cambie el estado.
     */
    protected static function booted(): void
    {
        static::saving(function (PhysicalProduct $unit) {
            if (! $unit->isDirty('status')) {
                return;
            }

            if ($unit->isDisposed()) {
                $unit->disposed_at ??= now();
            } else {
                $unit->disposed_at = null;
            }
        });
    }

    public function isDisposed(): bool
    {
        return in_array($this->status, self::DISPOSED_STATUSES);
    }

    // ==========================================
    // RELACIONES PERTENECIENTES (BelongsTo)
    // ==========================================

    /**
     * Variante del producto a la que pertenece esta prenda física (Talla y Color).
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * Detalle de compra origen de esta unidad física.
     */
    public function purchaseDetail(): BelongsTo
    {
        return $this->belongsTo(PurchaseDetail::class, 'purchase_detail_id');
    }

    public function physicalProducts(): HasMany
    {
        return $this->hasMany(PhysicalProduct::class, 'product_variant_id');
    }
    // ==========================================
    // SCOPES DE ESTADO
    // ==========================================

    public function rentalDetails(): HasMany
    {
        return $this->hasMany(RentalDetail::class);
    }

    public function saleDetails(): HasMany
    {
        return $this->hasMany(SaleDetail::class);
    }

    public function reservedClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'reserved_client_id')->withTrashed();
    }

    /**
     * Está separada para un cliente (la marca no bloquea venderla ni alquilarla).
     */
    public function isReserved(): bool
    {
        return $this->reserved_client_id !== null;
    }

    /**
     * La fecha de la separación ya pasó y nadie la liberó.
     */
    public function isReservationExpired(): bool
    {
        return $this->isReserved() && $this->reserved_until?->isBefore(today());
    }

    public function scopeReserved(Builder $query): Builder
    {
        return $query->whereNotNull('reserved_client_id');
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(ItemMaintenance::class);
    }

    /**
     * Prendas que nunca se operaron: disponibles, sin alquileres, ventas ni
     * mantenimientos. Son las únicas que se pueden eliminar al corregir
     * (reducir) la cantidad de una compra.
     */
    public function scopeNeverOperated(Builder $query): Builder
    {
        return $query->where('status', 'available')
            ->whereDoesntHave('rentalDetails')
            ->whereDoesntHave('saleDetails')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('item_maintenances')
                ->whereColumn('item_maintenances.physical_product_id', 'physical_products.id'));
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    public function scopeRented($query)
    {
        return $query->where('status', 'rented');
    }

    public function scopeInMaintenance($query)
    {
        return $query->where('status', 'maintenance');
    }
}
