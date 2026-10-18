<?php

namespace App\Livewire\Core;

use App\Models\AuditLog;
use App\Services\Core\CurrentAccount;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Bitácora del negocio: qué se editó o eliminó, quién, cuándo y los valores
 * de antes y después. Solo lectura.
 */
#[Title('Auditoría')]
class AuditIndex extends Component
{
    use WithPagination;

    /**
     * Nombres legibles de los campos más comunes (el resto se muestra tal cual).
     */
    public const FIELD_LABELS = [
        'name' => 'Nombre', 'code' => 'Código', 'price' => 'Precio de venta', 'purchase_price' => 'Precio de compra', 'sale_price' => 'Precio de venta',
        'rental_price' => 'Precio de alquiler', 'amount' => 'Monto', 'total' => 'Total', 'subtotal' => 'Subtotal',
        'tax' => 'IGV', 'status' => 'Estado', 'notes' => 'Notas', 'description' => 'Descripción',
        'category' => 'Categoría', 'concept' => 'Concepto', 'expense_date' => 'Fecha del gasto',
        'category_id' => 'Categoría', 'brand_id' => 'Marca', 'client_id' => 'Cliente', 'supplier_id' => 'Proveedor',
        'document_type' => 'Tipo de documento', 'document_number' => 'N.º de documento', 'quantity' => 'Cantidad',
        'unit_cost' => 'Costo unitario', 'unit_price' => 'Precio unitario', 'current_unit_value' => 'Valor actual',
        'is_active' => 'Activo', 'cancelled_at' => 'Anulado el', 'cancelled_by' => 'Anulado por (usuario)',
        'cancellation_reason' => 'Motivo de anulación', 'display_name' => 'Nombre', 'company_name' => 'Razón social',
        'email' => 'Correo', 'mobile' => 'Celular', 'phone' => 'Teléfono', 'address' => 'Dirección',
        'gender' => 'Género', 'allow_sale' => 'Se vende', 'allow_rental' => 'Se alquila',
        'deposit_amount' => 'Garantía', 'rental_total' => 'Total del alquiler', 'penalty_total' => 'Cobro por daños',
        'has_invoice_pending' => 'Por facturar', 'sale_date' => 'Fecha de venta', 'issue_date' => 'Fecha de emisión',
        'deleted_at' => 'Eliminado el', 'created_at' => 'Creado el', 'updated_at' => 'Actualizado el',
    ];

    #[Url(as: 'evento')]
    public string $event = '';

    #[Url(as: 'tipo')]
    public string $modelType = '';

    #[Url(as: 'usuario')]
    public string $userId = '';

    #[Url(as: 'desde')]
    public string $dateFrom = '';

    #[Url(as: 'hasta')]
    public string $dateTo = '';

    public ?int $detailId = null;

    public bool $showDetail = false;

    public function updated(string $property): void
    {
        if (in_array($property, ['event', 'modelType', 'userId', 'dateFrom', 'dateTo'], true)) {
            $this->resetPage();
        }
    }

    /**
     * audit_logs no usa BelongsToAccount (ver AuditLog): aquí siempre se filtra por el negocio actual.
     */
    protected function baseQuery(): Builder
    {
        return AuditLog::query()->where('account_id', app(CurrentAccount::class)->requiredId());
    }

    #[Computed]
    public function logs(): LengthAwarePaginator
    {
        return $this->baseQuery()
            ->when($this->event !== '', fn (Builder $query) => $query->where('event', $this->event))
            ->when($this->modelType !== '', fn (Builder $query) => $query->where('auditable_type', $this->modelType))
            ->when($this->userId !== '', fn (Builder $query) => $query->where('user_id', (int) $this->userId))
            ->when($this->dateFrom !== '', fn (Builder $query) => $query->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn (Builder $query) => $query->whereDate('created_at', '<=', $this->dateTo))
            ->orderByDesc('id')
            ->paginate(25);
    }

    /**
     * Usuarios que aparecen en la bitácora del negocio: id => nombre.
     */
    #[Computed]
    public function users(): Collection
    {
        return $this->baseQuery()->whereNotNull('user_id')->distinct()->orderBy('user_name')->pluck('user_name', 'user_id');
    }

    #[Computed]
    public function detail(): ?AuditLog
    {
        return $this->detailId ? $this->baseQuery()->find($this->detailId) : null;
    }

    public function show(int $id): void
    {
        $this->detailId = $id;
        $this->showDetail = true;
    }

    public static function fieldLabel(string $field): string
    {
        return self::FIELD_LABELS[$field] ?? $field;
    }

    public static function displayValue(mixed $value): string
    {
        return match (true) {
            $value === null, $value === '' => '—',
            is_bool($value) => $value ? 'Sí' : 'No',
            is_array($value) => (string) json_encode($value, JSON_UNESCAPED_UNICODE),
            default => (string) $value,
        };
    }

    public function render(): View
    {
        return view('livewire.core.audit-index');
    }
}
