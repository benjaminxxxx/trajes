<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Separación (reserva) de una prenda física para un cliente. Es solo una
     * marca informativa: no impide vender ni alquilar la prenda.
     */
    public function up(): void
    {
        Schema::table('physical_products', function (Blueprint $table) {
            $table->foreignId('reserved_client_id')->nullable()->after('disposed_at')->constrained('clients')->nullOnDelete();
            $table->date('reserved_until')->nullable()->after('reserved_client_id');
            $table->string('reservation_note', 255)->nullable()->after('reserved_until');
            $table->foreignId('reserved_by')->nullable()->after('reservation_note')->constrained('users')->nullOnDelete();
            $table->timestamp('reserved_at')->nullable()->after('reserved_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('physical_products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reserved_client_id');
            $table->dropConstrainedForeignId('reserved_by');
            $table->dropColumn(['reserved_until', 'reservation_note', 'reserved_at']);
        });
    }
};
