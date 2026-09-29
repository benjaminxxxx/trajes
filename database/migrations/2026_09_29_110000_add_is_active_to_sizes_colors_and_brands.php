<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tallas, colores y marcas en desuso se desactivan en lugar de renombrarse:
     * dejan de ofrecerse, pero el historial (ventas, alquileres) queda intacto.
     */
    public function up(): void
    {
        foreach (['sizes', 'colors', 'brands'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('name');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['sizes', 'colors', 'brands'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }
    }
};
