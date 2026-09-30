<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Factura o boleta electrónica de una venta, tal como respondió el servicio de
 * facturación. Se guarda cada intento: así se sabe qué falta volver a enviar.
 */
class SunatDocument extends Model
{
    use BelongsToAccount;

    public const STATUS_PENDING = 'pendiente';

    public const STATUS_ACCEPTED = 'aceptado';

    public const STATUS_OBSERVED = 'observado';

    public const STATUS_REJECTED = 'rechazado';

    /** El servicio lo registró pero SUNAT no respondió: el servicio lo reenvía solo. */
    public const STATUS_SEND_ERROR = 'error_envio';

    /** No hubo respuesta del servicio de facturación: hay que reintentar desde aquí. */
    public const STATUS_OFFLINE = 'sin_conexion';

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Pendiente en SUNAT',
        self::STATUS_ACCEPTED => 'Aceptado',
        self::STATUS_OBSERVED => 'Aceptado con observaciones',
        self::STATUS_REJECTED => 'Rechazado',
        self::STATUS_SEND_ERROR => 'Pendiente en SUNAT',
        self::STATUS_OFFLINE => 'Sin respuesta',
    ];

    public const STATUS_COLORS = [
        self::STATUS_PENDING => 'amber',
        self::STATUS_ACCEPTED => 'emerald',
        self::STATUS_OBSERVED => 'lime',
        self::STATUS_REJECTED => 'red',
        self::STATUS_SEND_ERROR => 'amber',
        self::STATUS_OFFLINE => 'orange',
    ];

    /** Estados en los que el comprobante existe (o puede llegar a existir) en SUNAT. */
    public const LIVE_STATUSES = [
        self::STATUS_PENDING, self::STATUS_ACCEPTED, self::STATUS_OBSERVED, self::STATUS_SEND_ERROR, self::STATUS_OFFLINE,
    ];

    protected $fillable = [
        'sale_id',
        'user_id',
        'reference',
        'remote_id',
        'environment',
        'document_type',
        'series',
        'number',
        'issue_date',
        'customer_document_type',
        'customer_document_number',
        'customer_name',
        'total',
        'status',
        'sunat_code',
        'sunat_description',
        'sunat_notes',
        'hash',
        'attempts',
        'request_payload',
        'credit_note_required',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'total' => 'decimal:2',
        'sunat_notes' => 'array',
        'request_payload' => 'array',
        'credit_note_required' => 'boolean',
        'number' => 'integer',
        'attempts' => 'integer',
    ];

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Comprobante real (producción) que existe o puede llegar a existir en SUNAT.
     *
     * Los de modo prueba (beta) no cuentan: no tienen valor tributario, así que no marcan la venta
     * como facturada, no impiden volver a facturarla y no requieren nota de crédito.
     * Sin entorno conocido se asume producción (lo seguro es bloquear).
     *
     * @param  Builder<SunatDocument>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->whereIn('status', self::LIVE_STATUSES)
            ->where(fn (Builder $q) => $q->whereNull('environment')->orWhere('environment', '!=', 'beta'));
    }

    /**
     * Emitido en modo prueba (SUNAT beta): sin valor tributario.
     */
    public function isTest(): bool
    {
        return $this->environment === 'beta';
    }

    public function fullNumber(): string
    {
        return $this->number ? "{$this->series}-{$this->number}" : "{$this->series}-…";
    }

    public function isValid(): bool
    {
        return in_array($this->status, [self::STATUS_ACCEPTED, self::STATUS_OBSERVED], true);
    }

    /**
     * Aún sin respuesta definitiva de SUNAT: se puede consultar o reintentar.
     */
    public function isAwaiting(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_SEND_ERROR, self::STATUS_OFFLINE], true);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'zinc';
    }

    public function typeLabel(): string
    {
        return $this->document_type === 'factura' ? 'Factura' : 'Boleta';
    }
}
