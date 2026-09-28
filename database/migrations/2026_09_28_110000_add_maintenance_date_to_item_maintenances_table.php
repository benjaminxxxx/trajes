<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fecha en que se hizo el mantenimiento (puede registrarse después), para
     * sumarlo en el mes correcto del dashboard.
     */
    public function up(): void
    {
        Schema::table('item_maintenances', function (Blueprint $table) {
            $table->date('maintenance_date')->nullable()->after('description')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('item_maintenances', function (Blueprint $table) {
            $table->dropIndex(['maintenance_date']);
            $table->dropColumn('maintenance_date');
        });
    }
};
