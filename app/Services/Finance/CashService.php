<?php

namespace App\Services\Finance;

use App\Models\CashMovement;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Único punto de escritura de la caja. Toda operación con dinero (venta,
 * alquiler, compra, gasto, mantenimiento, aporte, retiro) pasa por aquí.
 * Nada se edita ni se borra: lo que se deshace se registra con su inverso.
 */
class CashService
{
    public function income(string $origin, float $amount, string $description, ?Model $reference = null, array $options = []): ?CashMovement
    {
        return $this->record(CashMovement::TYPE_INCOME, $origin, $amount, $description, $reference, $options);
    }

    public function expense(string $origin, float $amount, string $description, ?Model $reference = null, array $options = []): ?CashMovement
    {
        return $this->record(CashMovement::TYPE_EXPENSE, $origin, $amount, $description, $reference, $options);
    }

    /**
     * Saldo de caja del negocio actual.
     */
    public function balance(): float
    {
        $totals = CashMovement::query()
            ->selectRaw('type, COALESCE(SUM(amount), 0) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        return round((float) ($totals[CashMovement::TYPE_INCOME] ?? 0) - (float) ($totals[CashMovement::TYPE_EXPENSE] ?? 0), 2);
    }

    /**
     * Movimiento hecho a mano: inyección de capital, retiro, otro ingreso u otro egreso.
     */
    public function registerManual(string $origin, float $amount, string $description): CashMovement
    {
        $type = CashMovement::MANUAL_ORIGINS[$origin]
            ?? throw new \InvalidArgumentException("[{$origin}] no es un movimiento manual de caja.");

        if ($amount <= 0) {
            throw new \RuntimeException('El monto debe ser mayor a cero.');
        }

        return $this->record($type, $origin, $amount, trim($description));
    }

    /**
     * Efecto inverso de un movimiento manual (ej. me equivoqué o era una prueba).
     * Los movimientos de una venta, compra, etc. se revierten anulando esa operación.
     */
    public function reverse(CashMovement $movement, string $reason): CashMovement
    {
        return DB::transaction(function () use ($movement, $reason) {
            $movement = CashMovement::whereKey($movement->id)->lockForUpdate()->firstOrFail();

            if (! $movement->isManual()) {
                throw new \RuntimeException('Este movimiento viene de una operación ('.$movement->originLabel().'): anúlala desde su módulo y la caja se ajusta sola.');
            }

            if ($movement->reverses_id) {
                throw new \RuntimeException('Este movimiento ya es una anulación.');
            }

            if ($movement->reversal()->exists()) {
                throw new \RuntimeException('Este movimiento ya fue anulado.');
            }

            return $this->record(
                $movement->isIncome() ? CashMovement::TYPE_EXPENSE : CashMovement::TYPE_INCOME,
                $movement->origin,
                (float) $movement->amount,
                'Anulación de «'.$movement->description.'»: '.trim($reason),
                options: ['reverses' => $movement],
            );
        });
    }

    /**
     * @param  array{payment_method?: ?string, date?: CarbonInterface|string|null, reverses?: ?CashMovement}  $options
     */
    protected function record(string $type, string $origin, float $amount, string $description, ?Model $reference = null, array $options = []): ?CashMovement
    {
        $amount = round($amount, 2);

        // Sin dinero de por medio (ej. alquiler sin garantía) no hay movimiento.
        if ($amount <= 0) {
            return null;
        }

        $movement = new CashMovement([
            'user_id' => Auth::id(),
            'type' => $type,
            'origin' => $origin,
            'amount' => $amount,
            'payment_method' => $options['payment_method'] ?? 'cash',
            'reference_id' => $reference?->getKey(),
            'reference_type' => $reference?->getMorphClass(),
            'reverses_id' => ($options['reverses'] ?? null)?->id,
            'description' => $description,
        ]);

        // cash_movements no tiene fecha propia: created_at es la fecha del
        // movimiento (un gasto de ayer no debe caer en la caja de hoy).
        if (! empty($options['date'])) {
            $movement->created_at = $options['date'];
        }

        $movement->save();

        return $movement;
    }
}
