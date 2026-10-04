<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Sin WithoutModelEvents: los eventos de los modelos son necesarios (asignan
 * el negocio actual a cada registro, marcan fechas de baja, auditan...).
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            UserSeeder::class,
            CategoryAndAttributeSeeder::class,
            // ClientSeeder::class,
            // SupplierSeeder::class,
            // ProductCatalogSeeder::class,
            // AssetSeeder::class,
        ]);
    }
}
