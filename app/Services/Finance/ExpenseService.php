<?php

namespace App\Services\Finance;

use App\Models\CashMovement;
use App\Models\OperationalExpense;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Único punto de escritura de gastos operativos. Cada gasto genera su
 * movimiento de caja (egreso), y al eliminarlo se elimina también ese
 * movimiento; ambas eliminaciones quedan en audit_logs.
 */
class ExpenseService
{
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

            $movement = new CashMovement([
                'user_id' => Auth::id(),
                'type' => CashMovement::TYPE_EXPENSE,
                'origin' => CashMovement::ORIGIN_OPERATIONAL_EXPENSE,
                'amount' => $expense->amount,
                'payment_method' => 'cash',
                'reference_id' => $expense->id,
                'reference_type' => $expense->getMorphClass(),
                'description' => "{$expense->category} · {$expense->concept}",
            ]);

            // cash_movements no tiene columna de fecha propia: usamos created_at
            // para que un gasto de ayer no aparezca en la caja de hoy.
            $movement->created_at = $expense->expense_date;
            $movement->save();

            return $expense;
        });
    }

    public function delete(OperationalExpense $expense): void
    {
        DB::transaction(function () use ($expense) {
            // Uno por uno (no en masa) para que cada eliminación pase por el trait de auditoría.
            $expense->cashMovements()->get()->each->delete();
            $expense->delete();
        });
    }
}
