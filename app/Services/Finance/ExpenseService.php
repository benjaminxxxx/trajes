<?php

namespace App\Services\Finance;

use App\Models\CashMovement;
use App\Models\OperationalExpense;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Único punto de escritura de gastos operativos. Cada gasto genera su egreso
 * de caja. Un gasto no se elimina: se anula, y la anulación devuelve el
 * dinero a caja con un reingreso (la historia queda intacta).
 */
class ExpenseService
{
    public function __construct(protected CashService $cash) {}

    public function register(array $data): OperationalExpense
    {
        return DB::transaction(function () use ($data) {
            $expense = OperationalExpense::create([
                'user_id' => Auth::id(),
                'category' => trim($data['category']),
                'concept' => trim($data['concept']),
                'amount' => $data['amount'],
                'expense_date' => $data['expense_date'],
                'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
            ]);

            $this->cash->expense(
                CashMovement::ORIGIN_OPERATIONAL_EXPENSE,
                (float) $expense->amount,
                "{$expense->category} · {$expense->concept}",
                $expense,
                ['date' => $expense->expense_date],
            );

            return $expense;
        });
    }

    public function cancel(OperationalExpense $expense, string $reason): OperationalExpense
    {
        return DB::transaction(function () use ($expense, $reason) {
            $expense = OperationalExpense::whereKey($expense->id)->lockForUpdate()->firstOrFail();

            if ($expense->isCancelled()) {
                throw new \RuntimeException('Este gasto ya está anulado.');
            }

            $expense->update([
                'cancelled_at' => now(),
                'cancelled_by' => Auth::id(),
                'cancellation_reason' => trim($reason),
            ]);

            $this->cash->income(
                CashMovement::ORIGIN_OPERATIONAL_EXPENSE,
                (float) $expense->amount,
                "Reingreso por anulación de gasto: {$expense->category} · {$expense->concept}",
                $expense,
                ['reverses' => $expense->cashMovements()->where('type', CashMovement::TYPE_EXPENSE)->oldest('id')->first()],
            );

            return $expense;
        });
    }
}
