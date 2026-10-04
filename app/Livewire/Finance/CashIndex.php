<?php

namespace App\Livewire\Finance;

use App\Models\CashMovement;
use App\Services\Finance\CashService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Caja del negocio: saldo que debe coincidir con la cuenta bancaria y el
 * libro de todos los movimientos. Aquí solo se registran a mano los aportes,
 * retiros y otros ingresos/egresos; ventas, compras, gastos, etc. llegan
 * solos desde su módulo. Nada se edita ni se borra: se anula con el inverso.
 */
#[Title('Caja')]
class CashIndex extends Component
{
    use WithPagination;

    #[Url(as: 'tipo')]
    public string $type = '';

    #[Url(as: 'origen')]
    public string $origin = '';

    #[Url(as: 'desde')]
    public string $dateFrom = '';

    #[Url(as: 'hasta')]
    public string $dateTo = '';

    // Movimiento manual
    public bool $showForm = false;

    public string $movementOrigin = CashMovement::ORIGIN_CAPITAL_INJECTION;

    public float|string $amount = '';

    public string $description = '';

    // Anulación (efecto inverso)
    public bool $showReverse = false;

    public ?int $reverseId = null;

    public string $reverseReason = '';

    // Cuadre con el banco: saldo real de la cuenta.
    public float|string $bankBalance = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['type', 'origin', 'dateFrom', 'dateTo'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function balance(): float
    {
        return app(CashService::class)->balance();
    }

    protected function filteredQuery(): Builder
    {
        return CashMovement::query()
            ->when($this->type !== '', fn (Builder $query) => $query->where('type', $this->type))
            ->when($this->origin !== '', fn (Builder $query) => $query->where('origin', $this->origin))
            ->when($this->dateFrom !== '', fn (Builder $query) => $query->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn (Builder $query) => $query->whereDate('created_at', '<=', $this->dateTo));
    }

    #[Computed]
    public function movements(): LengthAwarePaginator
    {
        return $this->filteredQuery()
            ->with(['user:id,name', 'reversal:id,reverses_id,created_at', 'reverses:id,created_at'])
            ->orderByDesc('id')
            ->paginate(20);
    }

    /**
     * Ingresos y egresos de lo filtrado.
     *
     * @return array{income: float, expense: float}
     */
    #[Computed]
    public function totals(): array
    {
        $totals = $this->filteredQuery()->selectRaw('type, COALESCE(SUM(amount), 0) as total')->groupBy('type')->pluck('total', 'type');

        return [
            'income' => (float) ($totals[CashMovement::TYPE_INCOME] ?? 0),
            'expense' => (float) ($totals[CashMovement::TYPE_EXPENSE] ?? 0),
        ];
    }

    /**
     * Banco − caja. Positivo: en el banco hay más de lo que dice la caja.
     */
    #[Computed]
    public function bankDifference(): ?float
    {
        return is_numeric($this->bankBalance) ? round((float) $this->bankBalance - $this->balance, 2) : null;
    }

    public function openForm(string $origin = CashMovement::ORIGIN_CAPITAL_INJECTION): void
    {
        $this->authorize('caja.registrar');
        $this->resetValidation();
        $this->reset(['amount', 'description']);
        $this->movementOrigin = array_key_exists($origin, CashMovement::MANUAL_ORIGINS) ? $origin : CashMovement::ORIGIN_CAPITAL_INJECTION;
        $this->showForm = true;
    }

    public function save(CashService $service): void
    {
        $this->authorize('caja.registrar');

        $this->validate([
            'movementOrigin' => ['required', Rule::in(array_keys(CashMovement::MANUAL_ORIGINS))],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'description' => ['required', 'string', 'min:3', 'max:255'],
        ], [], ['movementOrigin' => 'tipo de movimiento', 'amount' => 'monto', 'description' => 'motivo']);

        $service->registerManual($this->movementOrigin, (float) $this->amount, $this->description);

        $this->showForm = false;
        unset($this->balance);
        Flux::toast(variant: 'success', text: 'Movimiento registrado. Saldo de caja: S/ '.number_format($this->balance, 2).'.');
    }

    public function confirmReverse(int $id): void
    {
        $this->authorize('caja.registrar');
        $this->resetValidation();
        $this->reverseId = $id;
        $this->reverseReason = '';
        $this->showReverse = true;
    }

    #[Computed]
    public function pendingReverse(): ?CashMovement
    {
        return $this->reverseId ? CashMovement::find($this->reverseId) : null;
    }

    public function reverse(CashService $service): void
    {
        $this->authorize('caja.registrar');

        $this->validate(
            ['reverseReason' => ['required', 'string', 'min:3', 'max:200']],
            ['reverseReason.required' => 'Indica el motivo de la anulación.'],
            ['reverseReason' => 'motivo'],
        );

        try {
            $service->reverse(CashMovement::findOrFail($this->reverseId), $this->reverseReason);

            $this->showReverse = false;
            $this->reverseId = null;
            unset($this->balance);
            Flux::toast(variant: 'success', text: 'Se registró el movimiento inverso.');
        } catch (\RuntimeException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function render(): View
    {
        return view('livewire.finance.cash-index');
    }
}
