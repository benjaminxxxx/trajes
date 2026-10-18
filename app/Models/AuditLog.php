<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Bitácora de eliminaciones. account_id es opcional (las acciones de la
 * plataforma no pertenecen a un negocio), por eso no usa BelongsToAccount:
 * al consultarla desde un negocio, filtrar por account_id.
 */
class AuditLog extends Model
{
    // Solo created_at: una bitácora no se modifica.
    public const UPDATED_AT = null;

    public const EVENTS = [
        'updated' => 'Edición',
        'deleted' => 'Eliminación',
    ];

    /**
     * Nombre legible de lo auditado (clase => etiqueta).
     */
    public const MODEL_LABELS = [
        Product::class => 'Producto',
        ProductVariant::class => 'Variante de producto',
        ProductCharacteristic::class => 'Característica de producto',
        Category::class => 'Categoría',
        Brand::class => 'Marca',
        Size::class => 'Talla',
        Color::class => 'Color',
        Attribute::class => 'Característica',
        Client::class => 'Cliente',
        Supplier::class => 'Proveedor',
        Purchase::class => 'Compra',
        PurchaseDetail::class => 'Línea de compra',
        Sale::class => 'Venta',
        Rental::class => 'Alquiler',
        Asset::class => 'Bien del negocio',
        OperationalExpense::class => 'Gasto operativo',
        ItemMaintenance::class => 'Mantenimiento',
        DepreciationRule::class => 'Regla de desgaste',
        BillingSetting::class => 'Configuración de facturación',
    ];

    protected $fillable = [
        'account_id',
        'auditable_type',
        'auditable_id',
        'event',
        'user_id',
        'user_name',
        'old_values',
        'new_values',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function modelLabel(): string
    {
        return self::MODEL_LABELS[$this->auditable_type] ?? class_basename($this->auditable_type);
    }

    public function eventLabel(): string
    {
        return self::EVENTS[$this->event] ?? $this->event;
    }

    /**
     * Campos afectados con su valor anterior y nuevo (en una eliminación solo hay anterior).
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function changes(): array
    {
        $old = $this->old_values ?? [];
        $new = $this->new_values ?? [];

        return collect(array_keys($old + $new))
            ->mapWithKeys(fn (string $field) => [$field => ['old' => $old[$field] ?? null, 'new' => $new[$field] ?? null]])
            ->all();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Apunta al modelo original; devuelve null porque ya no existe (fue eliminado).
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
