<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use BelongsToAccount, HasFactory, SoftDeletes;

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const DOCUMENT_TYPES = [
        'nota_venta' => 'Nota de venta',
        'boleta' => 'Boleta',
        'factura' => 'Factura',
    ];

    protected $fillable = [
        'code',
        'client_id',
        'user_id',
        'document_type',
        'document_number',
        'invoice_date',
        'has_invoice_pending',
        'sale_date',
        'subtotal',
        'tax',
        'total',
        'status',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'notes',
    ];

    protected $casts = [
        'cancelled_at' => 'datetime',
        'invoice_date' => 'datetime',
        'sale_date' => 'datetime',
        'has_invoice_pending' => 'boolean',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(SaleDetail::class);
    }

    /**
     * Consumibles entregados con la venta (portaternos, correas...).
     */
    public function assetDeliveries(): HasMany
    {
        return $this->hasMany(SaleAssetDelivery::class);
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * Comprobantes electrónicos emitidos o intentados (historial completo).
     */
    public function sunatDocuments(): HasMany
    {
        return $this->hasMany(SunatDocument::class)->latest('id');
    }

    /**
     * El comprobante vigente de producción: válido o todavía esperando respuesta de SUNAT.
     * Los de modo prueba no cuentan (ver SunatDocument::scopeLive).
     */
    public function liveSunatDocument(): HasOne
    {
        return $this->hasOne(SunatDocument::class)->ofMany(['id' => 'max'], fn ($q) => $q->live());
    }

    /**
     * Último comprobante emitido en modo prueba (solo informativo: no bloquea volver a facturar).
     */
    public function latestTestSunatDocument(): HasOne
    {
        return $this->hasOne(SunatDocument::class)->ofMany(['id' => 'max'], fn ($q) => $q->where('environment', 'beta'));
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }
}
