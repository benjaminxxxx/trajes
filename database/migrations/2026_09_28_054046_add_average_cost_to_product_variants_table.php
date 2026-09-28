<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Costo unitario promedio ponderado (sin IGV) de la variante. Se recalcula
     * en cada compra y es el costo que se copia al detalle de una venta
     * cuando no hay una prenda física con su propio costo.
     */
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->decimal('average_cost', 12, 4)->default(0)->after('rental_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('average_cost');
        });
    }
};
