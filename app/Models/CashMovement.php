<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CashMovement extends Model
{
    use HasFactory;

    public const TYPE_INCOME = 'income';
    public const TYPE_EXPENSE = 'expense';

    public const ORIGIN_RENTAL_PAYMENT = 'rental_payment';
    public const ORIGIN_SALE_PAYMENT = 'sale_payment';
    public const ORIGIN_EXTRA_SERVICE_PAYMENT = 'extra_service_payment';
    public const ORIGIN_PENALTY_FEE = 'penalty_fee';
    public const ORIGIN_OPERATIONAL_EXPENSE = 'operational_expense';
    public const ORIGIN_PURCHASE_EXPENSE = 'purchase_expense';
    public const ORIGIN_OTHER = 'other';

    protected $fillable = [
        'user_id',
        'type',
        'origin',
        'amount',
        'payment_method',
        'reference_id',
        'reference_type',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}