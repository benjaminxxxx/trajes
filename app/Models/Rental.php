<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rental extends Model
{
    use AuditsChanges, BelongsToAccount, HasFactory, SoftDeletes;

    public const STATUS_RESERVED = 'reserved';

    public const STATUS_OUT = 'out';

    public const STATUS_RETURNED = 'returned';

    public const STATUS_CONVERTED_TO_SALE = 'converted_to_sale';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'code',
        'client_id',
        'user_id',
        'pickup_date',
        'expected_return_date',
        'actual_return_date',
        'rental_total',
        'deposit_amount',
        'penalty_total',
        'status',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'notes',
        'return_notes',
    ];

    public const STATUS_LABELS = [
        self::STATUS_RESERVED => 'Reservado',
        self::STATUS_OUT => 'En curso',
        self::STATUS_RETURNED => 'Devuelto',
        self::STATUS_CONVERTED_TO_SALE => 'Convertido a venta',
        self::STATUS_CANCELLED => 'Anulado',
    ];

    protected $casts = [
        'cancelled_at' => 'datetime',
        'pickup_date' => 'datetime',
        'expected_return_date' => 'datetime',
        'actual_return_date' => 'datetime',
        'rental_total' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'penalty_total' => 'decimal:2',
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
        return $this->hasMany(RentalDetail::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(RentalMedia::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(RentalMedia::class)->where('type', 'dni')->orderBy('id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Only rentals whose garments are still out (or reserved) can be cancelled;
     * returned or converted rentals already closed their garment cycle.
     */
    public function isCancellable(): bool
    {
        return in_array($this->status, [self::STATUS_RESERVED, self::STATUS_OUT]);
    }

    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_OUT && $this->expected_return_date?->isPast();
    }
}
