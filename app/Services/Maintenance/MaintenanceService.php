<?php

namespace App\Services\Maintenance;

use App\Models\ItemMaintenance;
use App\Models\PhysicalProduct;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Registro de mantenimientos por prenda física. El costo queda en su
 * historial (ficha de la prenda) y en el dashboard como gasto de mantenimiento.
 */
class MaintenanceService
{
    /**
     * Estados que se pueden cambiar al registrar el mantenimiento. Una prenda
     * alquilada, vendida o dada de baja conserva su estado.
     */
    protected const ADJUSTABLE_STATUSES = ['available', 'maintenance'];

    /**
     * `after_status`: keep (sin cambios) | available | maintenance.
     *
     * @param  array{type: string, cost: float|string, provider_name?: ?string, description?: ?string, maintenance_date: string, after_status?: string}  $data
     */
    public function register(PhysicalProduct $unit, array $data): ItemMaintenance
    {
        return DB::transaction(function () use ($unit, $data) {
            $unit = PhysicalProduct::whereKey($unit->id)->lockForUpdate()->firstOrFail();

            $maintenance = ItemMaintenance::create([
                'physical_product_id' => $unit->id,
                'user_id' => Auth::id(),
                // Si viene de un alquiler, se vincula al último en que salió.
                'rental_id' => $unit->rentalDetails()->latest('id')->value('rental_id'),
                'type' => $data['type'],
                'cost' => round((float) $data['cost'], 2),
                'provider_name' => filled($data['provider_name'] ?? null) ? trim($data['provider_name']) : null,
                'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
                'maintenance_date' => $data['maintenance_date'],
            ]);

            $afterStatus = $data['after_status'] ?? 'keep';

            if ($afterStatus !== 'keep' && in_array($unit->status, self::ADJUSTABLE_STATUSES)) {
                $unit->update([
                    'status' => $afterStatus === 'maintenance' ? 'maintenance' : 'available',
                    'status_note' => $afterStatus === 'maintenance'
                        ? (ItemMaintenance::TYPES[$data['type']] ?? 'Mantenimiento').' en curso'
                        : null,
                ]);
            }

            return $maintenance;
        });
    }
}
