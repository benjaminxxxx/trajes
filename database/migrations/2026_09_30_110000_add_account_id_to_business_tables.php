<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tablas del negocio: cada fila pertenece a una cuenta. El filtro lo aplica
     * el trait BelongsToAccount; AccountIsolationTest falla si una tabla nueva
     * no está aquí ni en la lista de tablas de plataforma o pivote.
     *
     * @var list<string>
     */
    public const TABLES = [
        'assets', 'asset_movements', 'attributes', 'attribute_values', 'brands',
        'cash_movements', 'categories', 'clients', 'colors', 'depreciation_rules',
        'extra_services', 'extra_service_details', 'inventory_movements',
        'item_maintenances', 'operational_expenses', 'physical_products',
        'product_media', 'product_variants', 'products', 'purchase_details',
        'purchases', 'rental_details', 'rental_media', 'rentals',
        'sale_asset_deliveries', 'sale_details', 'sales', 'sizes', 'suppliers',
    ];

    /**
     * Índices únicos que pasan a ser únicos dentro de cada cuenta: dos negocios
     * pueden tener la venta V-260930-0001 o el mismo cliente con su DNI.
     *
     * @var array<string, array<string, list<string>>>
     */
    public const UNIQUES = [
        'brands' => ['brands_slug_unique' => ['slug']],
        'categories' => ['categories_slug_unique' => ['slug']],
        'clients' => ['clients_document_type_document_number_unique' => ['document_type', 'document_number']],
        'depreciation_rules' => ['depreciation_rules_min_rentals_unique' => ['min_rentals']],
        'extra_services' => ['extra_services_code_unique' => ['code']],
        'physical_products' => ['physical_products_code_unique' => ['code']],
        'product_variants' => ['product_variants_sku_unique' => ['sku']],
        'products' => ['products_code_unique' => ['code'], 'products_slug_unique' => ['slug']],
        'rentals' => ['rentals_code_unique' => ['code']],
        'sales' => ['sales_code_unique' => ['code']],
        'suppliers' => [
            'suppliers_code_unique' => ['code'],
            'suppliers_document_type_document_number_unique' => ['document_type', 'document_number'],
        ],
    ];

    public function up(): void
    {
        $accountId = DB::table('accounts')->min('id');

        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('account_id')->nullable()->after('id')->constrained();
            });

            if ($accountId) {
                DB::table($tableName)->update(['account_id' => $accountId]);
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('account_id')->nullable(false)->change();
            });
        }

        // Registros de auditoría: la cuenta es opcional (las acciones de la plataforma no tienen cuenta).
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        if ($accountId) {
            DB::table('audit_logs')->update(['account_id' => $accountId]);
        }

        foreach (self::UNIQUES as $tableName => $indexes) {
            Schema::table($tableName, function (Blueprint $table) use ($indexes) {
                foreach ($indexes as $indexName => $columns) {
                    $table->dropUnique($indexName);
                    $table->unique(['account_id', ...$columns]);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::UNIQUES as $tableName => $indexes) {
            Schema::table($tableName, function (Blueprint $table) use ($indexes) {
                foreach ($indexes as $indexName => $columns) {
                    $table->dropUnique(['account_id', ...$columns]);
                    $table->unique($columns, $indexName);
                }
            });
        }

        foreach ([...self::TABLES, 'audit_logs'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('account_id');
            });
        }
    }
};
