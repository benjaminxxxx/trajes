<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - physical_products.disposed_at: fecha exacta en que la prenda salió del
     *   inventario sin venderse (perdida, robada o dada de baja).
     * - rentals.return_notes: observaciones al recepcionar el alquiler.
     */
    public function up(): void
    {
        Schema::table('physical_products', function (Blueprint $table) {
            $table->timestamp('disposed_at')->nullable()->after('sold_at');
        });

        Schema::table('rentals', function (Blueprint $table) {
            $table->text('return_notes')->nullable()->after('notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('physical_products', function (Blueprint $table) {
            $table->dropColumn('disposed_at');
        });

        Schema::table('rentals', function (Blueprint $table) {
            $table->dropColumn('return_notes');
        });
    }
};
