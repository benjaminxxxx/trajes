<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Purchase extends Model
{
    use AuditsChanges, BelongsToAccount, HasFactory, SoftDeletes;

    protected $fillable = [
        'supplier_id',
        'user_id',
        'document_type',
        'document_number',
        'has_invoice_pending',
        'prices_include_tax',
        'issue_date',
        'subtotal',
        'tax',
        'total',
        'document_total',
        'document_tax',
        'status',
        'notes',
        'draft_items',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'has_invoice_pending' => 'boolean',
        'prices_include_tax' => 'boolean',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'document_total' => 'decimal:2',
        'document_tax' => 'decimal:2',
        'draft_items' => 'array',
    ];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_COMPLETED = 'completed';

    /**
     * Borrador: lo avanzado se guarda, pero aún no toca stock ni caja.
     */
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(PurchaseDetail::class);
    }
}
