<?php

namespace Database\Seeders;

use App\Models\Supplier;
use App\Services\Purchasing\SupplierService;
use Illuminate\Database\Seeder;

/**
 * 5 proveedores de demostración (Gamarra y marcas). Se crean con
 * SupplierService para que tengan su código PROV-000000 y display_name.
 * Idempotente: identifica a cada proveedor por su documento.
 */
class SupplierSeeder extends Seeder
{
    use RunsInAccount;

    public function run(SupplierService $service): void
    {
        $this->ensureAccount();

        foreach ($this->suppliers() as $supplier) {
            $exists = Supplier::withTrashed()
                ->where('document_type', $supplier['document_type'])
                ->where('document_number', $supplier['document_number'])
                ->exists();

            if (! $exists) {
                $service->crear([...$supplier, 'is_active' => true]);
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function suppliers(): array
    {
        return [
            // Lo usa también ProductCatalogSeeder para la compra inicial.
            ['type' => 'company', 'document_type' => 'RUC', 'document_number' => '20512345678', 'company_name' => 'Confecciones Gamarra S.A.C.', 'mobile' => '987111222', 'email' => 'ventas@confeccionesgamarra.pe', 'address' => 'Jr. Gamarra 1024, La Victoria, Lima', 'bank_name' => 'BCP', 'account_number' => '193-2456789-0-12'],
            ['type' => 'company', 'document_type' => 'RUC', 'document_number' => '20603458712', 'company_name' => 'Textiles Barrington Perú S.A.', 'mobile' => '016124578', 'email' => 'pedidos@barrington.com.pe', 'address' => 'Av. Argentina 2450, Cercado de Lima', 'bank_name' => 'BBVA', 'account_number' => '0011-0145-0200345678'],
            ['type' => 'company', 'document_type' => 'RUC', 'document_number' => '20548721369', 'company_name' => 'Novias y Quinceañeras Victoria E.I.R.L.', 'mobile' => '956874123', 'email' => 'atencion@noviasvictoria.pe', 'address' => 'Jr. Sebastián Barranca 1580, La Victoria, Lima', 'notes' => 'Vestidos de novia, quinceañera y fiesta.'],
            ['type' => 'company', 'document_type' => 'RUC', 'document_number' => '20614785236', 'company_name' => 'Calzados Trujillo Import S.A.C.', 'mobile' => '944123789', 'email' => 'ventas@calzadostrujillo.pe', 'address' => 'Av. América Norte 1850, Trujillo', 'bank_name' => 'Interbank', 'account_number' => '200-3004567891'],
            ['type' => 'individual', 'document_type' => 'DNI', 'document_number' => '40125873', 'names' => 'Julio César', 'paternal_last_name' => 'Mendoza', 'maternal_last_name' => 'Ccori', 'mobile' => '965478123', 'address' => 'Galería Guizado, Stand 214, Gamarra', 'notes' => 'Sastre: confección a medida de ternos para niño.'],
        ];
    }
}
