<?php

namespace App\Services\Purchasing;

use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

class SupplierService
{
    public function crear(array $data): Supplier
    {
        return DB::transaction(function () use ($data) {
            $data['code'] = $this->generateCode();
            $data['display_name'] = $this->calculateDisplayName($data);

            return Supplier::create($data);
        });
    }

    public function actualizar(Supplier $supplier, array $data): Supplier
    {
        return DB::transaction(function () use ($supplier, $data) {
            $data['display_name'] = $this->calculateDisplayName($data);
            $supplier->update($data);

            return $supplier;
        });
    }

    protected function calculateDisplayName(array $data): string
    {
        if (($data['type'] ?? 'individual') === 'company') {
            return trim($data['company_name'] ?? '');
        }

        $names = trim($data['names'] ?? '');
        $paternal = trim($data['paternal_last_name'] ?? '');
        $maternal = trim($data['maternal_last_name'] ?? '');

        return trim("{$names} {$paternal} {$maternal}");
    }

    protected function generateCode(): string
    {
        $lastId = Supplier::withTrashed()->max('id') ?? 0;
        return 'PROV-' . str_pad($lastId + 1, 6, '0', STR_PAD_LEFT);
    }
}