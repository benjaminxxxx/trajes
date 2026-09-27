<?php

namespace App\Models;

use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rental extends Model
{
    use HasFactory, SoftDeletes;

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
        'notes',
    ];

    protected $casts = [
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
}