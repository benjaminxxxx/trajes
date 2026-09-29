<?php

namespace App\Services\Rental;

use App\Models\Client;
use App\Models\PhysicalProduct;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Separación de prendas físicas para un cliente (ej. un terno para una boda
 * dentro de dos semanas). Es solo una marca visible: la prenda se puede
 * vender o alquilar igual si alguien llega antes; el sistema solo avisa.
 */
class ReservationService
{
    /**
     * Campos que dejan la prenda sin separación.
     */
    public const CLEARED = [
        'reserved_client_id' => null,
        'reserved_until' => null,
        'reservation_note' => null,
        'reserved_by' => null,
        'reserved_at' => null,
    ];

    /**
     * @param  array<int, int>  $unitIds
     */
    public function reserve(array $unitIds, Client $client, string $until, ?string $note = null): int
    {
        return DB::transaction(fn () => PhysicalProduct::whereKey($unitIds)
            ->whereNotIn('status', ['sold', ...PhysicalProduct::DISPOSED_STATUSES])
            ->update([
                'reserved_client_id' => $client->id,
                'reserved_until' => $until,
                'reservation_note' => filled($note) ? trim($note) : null,
                'reserved_by' => Auth::id(),
                'reserved_at' => now(),
            ]));
    }

    public function release(PhysicalProduct $unit): void
    {
        $unit->update(self::CLEARED);
    }

    /**
     * Al alquilar: si la prenda estaba separada para ese mismo cliente, la
     * separación se cumplió y se quita. Si era para otro cliente se mantiene.
     */
    public function fulfilledByRental(PhysicalProduct $unit, int $clientId): void
    {
        if ($unit->reserved_client_id === $clientId) {
            $unit->update(self::CLEARED);
        }
    }
}
