<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Libro de caja del negocio: cada sol que entra o sale. El saldo (ingresos −
 * egresos) debe coincidir con la cuenta bancaria.
 *
 * La historia no se edita ni se elimina: un error se corrige con el
 * movimiento inverso (`reverses_id`). Se escribe solo desde CashService.
 */
class CashMovement extends Model
{
    use BelongsToAccount, HasFactory;

    public const TYPE_INCOME = 'income';

    public const TYPE_EXPENSE = 'expense';

    public const ORIGIN_RENTAL_PAYMENT = 'rental_payment';

    public const ORIGIN_RENTAL_DEPOSIT = 'rental_deposit';

    public const ORIGIN_SALE_PAYMENT = 'sale_payment';

    public const ORIGIN_EXTRA_SERVICE_PAYMENT = 'extra_service_payment';

    public const ORIGIN_PENALTY_FEE = 'penalty_fee';

    public const ORIGIN_OPERATIONAL_EXPENSE = 'operational_expense';

    public const ORIGIN_PURCHASE_EXPENSE = 'purchase_expense';

    public const ORIGIN_ASSET_PURCHASE = 'asset_purchase';

    public const ORIGIN_MAINTENANCE = 'maintenance';

    public const ORIGIN_CAPITAL_INJECTION = 'capital_injection';

    public const ORIGIN_WITHDRAWAL = 'withdrawal';

    public const ORIGIN_OTHER_INCOME = 'other_income';

    public const ORIGIN_OTHER_EXPENSE = 'other_expense';

    public const ORIGIN_OTHER = 'other';

    public const ORIGINS = [
        self::ORIGIN_SALE_PAYMENT => 'Venta',
        self::ORIGIN_RENTAL_PAYMENT => 'Alquiler',
        self::ORIGIN_RENTAL_DEPOSIT => 'Garantía de alquiler',
        self::ORIGIN_PENALTY_FEE => 'Cobro por daños',
        self::ORIGIN_EXTRA_SERVICE_PAYMENT => 'Servicio extra',
        self::ORIGIN_PURCHASE_EXPENSE => 'Compra de mercadería',
        self::ORIGIN_ASSET_PURCHASE => 'Compra de bienes',
        self::ORIGIN_MAINTENANCE => 'Mantenimiento de prendas',
        self::ORIGIN_OPERATIONAL_EXPENSE => 'Gasto operativo',
        self::ORIGIN_CAPITAL_INJECTION => 'Inyección de capital',
        self::ORIGIN_WITHDRAWAL => 'Retiro de caja',
        self::ORIGIN_OTHER_INCOME => 'Otro ingreso',
        self::ORIGIN_OTHER_EXPENSE => 'Otro egreso',
        self::ORIGIN_OTHER => 'Otro',
    ];

    /**
     * Movimientos que se registran a mano desde Caja (origen => tipo). El
     * resto nace de su operación (venta, compra...) y se revierte anulándola.
     */
    public const MANUAL_ORIGINS = [
        self::ORIGIN_CAPITAL_INJECTION => self::TYPE_INCOME,
        self::ORIGIN_WITHDRAWAL => self::TYPE_EXPENSE,
        self::ORIGIN_OTHER_INCOME => self::TYPE_INCOME,
        self::ORIGIN_OTHER_EXPENSE => self::TYPE_EXPENSE,
    ];

    protected $fillable = [
        'user_id',
        'type',
        'origin',
        'amount',
        'payment_method',
        'reference_id',
        'reference_type',
        'reverses_id',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \LogicException('Los movimientos de caja no se editan: registra el movimiento inverso.');
        });

        static::deleting(function () {
            throw new \LogicException('Los movimientos de caja no se eliminan: registra el movimiento inverso.');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Movimiento original que este anula.
     */
    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_id');
    }

    /**
     * Movimiento que anuló a este, si lo hay.
     */
    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_id');
    }

    public function isIncome(): bool
    {
        return $this->type === self::TYPE_INCOME;
    }

    public function isManual(): bool
    {
        return array_key_exists($this->origin, self::MANUAL_ORIGINS);
    }

    public function originLabel(): string
    {
        return self::ORIGINS[$this->origin] ?? $this->origin;
    }

    /**
     * Efecto en el saldo: positivo si entra, negativo si sale.
     */
    public function signedAmount(): float
    {
        return $this->isIncome() ? (float) $this->amount : -(float) $this->amount;
    }
}
