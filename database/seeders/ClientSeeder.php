<?php

namespace Database\Seeders;

use App\Models\Client;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * 10 clientes de demostración: 8 personas naturales (DNI) y 2 empresas (RUC).
 * Idempotente: identifica a cada cliente por su documento.
 */
class ClientSeeder extends Seeder
{
    use RunsInAccount;

    public function run(): void
    {
        $this->ensureAccount();

        foreach ($this->clients() as $client) {
            $displayName = $client['type'] === 'company'
                ? $client['company_name']
                : trim("{$client['names']} {$client['last_name']}");

            Client::firstOrCreate(
                ['document_type' => $client['document_type'], 'document_number' => $client['document_number']],
                [
                    ...$client,
                    'uuid' => (string) Str::uuid(),
                    'display_name' => $displayName,
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function clients(): array
    {
        return [
            ['type' => 'individual', 'document_type' => 'DNI', 'document_number' => '45872310', 'names' => 'Ana María', 'last_name' => 'Morales Quispe', 'gender' => 'female', 'birth_date' => '1994-03-12', 'mobile' => '987654321', 'email' => 'ana.morales@correo.pe', 'address' => 'Av. Grau 450, La Victoria'],
            ['type' => 'individual', 'document_type' => 'DNI', 'document_number' => '70214587', 'names' => 'Luis Alberto', 'last_name' => 'Ramírez Huamán', 'gender' => 'male', 'birth_date' => '1990-07-25', 'mobile' => '956123478', 'email' => 'luis.ramirez@correo.pe', 'address' => 'Jr. Huanta 812, Cercado de Lima'],
            ['type' => 'individual', 'document_type' => 'DNI', 'document_number' => '48123695', 'names' => 'Rosa Elena', 'last_name' => 'Torres Mamani', 'gender' => 'female', 'birth_date' => '1987-11-02', 'mobile' => '945782136', 'email' => null, 'address' => 'Calle Los Pinos 230, San Juan de Lurigancho'],
            ['type' => 'individual', 'document_type' => 'DNI', 'document_number' => '72563014', 'names' => 'Jorge Enrique', 'last_name' => 'Vargas Flores', 'gender' => 'male', 'birth_date' => '1998-01-19', 'mobile' => '912345678', 'email' => 'jorge.vargas@correo.pe', 'address' => null],
            ['type' => 'individual', 'document_type' => 'DNI', 'document_number' => '46987125', 'names' => 'Carmen Rosa', 'last_name' => 'Chávez Rojas', 'gender' => 'female', 'birth_date' => '1992-05-30', 'mobile' => '998877665', 'email' => 'carmen.chavez@correo.pe', 'address' => 'Av. Túpac Amaru 1520, Comas'],
            ['type' => 'individual', 'document_type' => 'DNI', 'document_number' => '71458236', 'names' => 'Diego Armando', 'last_name' => 'Castillo Paredes', 'gender' => 'male', 'birth_date' => '2001-09-08', 'mobile' => '934567812', 'email' => null, 'address' => 'Jr. Ayacucho 345, Breña'],
            ['type' => 'individual', 'document_type' => 'DNI', 'document_number' => '43215789', 'names' => 'Milagros', 'last_name' => 'Gutiérrez Salazar', 'gender' => 'female', 'birth_date' => '1985-12-14', 'mobile' => '976543210', 'email' => 'milagros.gs@correo.pe', 'address' => 'Mz. B Lt. 12, Villa El Salvador'],
            ['type' => 'individual', 'document_type' => 'CE', 'document_number' => '001245789', 'names' => 'Valentina', 'last_name' => 'Pérez González', 'gender' => 'female', 'birth_date' => '1996-04-21', 'mobile' => '923456781', 'email' => 'valentina.perez@correo.com', 'address' => 'Av. Arequipa 2780, Lince'],
            ['type' => 'company', 'document_type' => 'RUC', 'document_number' => '20601234571', 'company_name' => 'Eventos Del Sol S.A.C.', 'mobile' => '014567890', 'email' => 'contacto@eventosdelsol.pe', 'address' => 'Av. Javier Prado Este 3450, San Borja', 'notes' => 'Organiza matrimonios; suele alquilar varios ternos a la vez.'],
            ['type' => 'company', 'document_type' => 'RUC', 'document_number' => '20557896413', 'company_name' => 'Colegio San Martín de Porres', 'mobile' => '016543210', 'email' => 'promociones@colegiosmp.edu.pe', 'address' => 'Jr. Junín 1200, Rímac', 'notes' => 'Alquila trajes para la fiesta de promoción.'],
        ];
    }
}
