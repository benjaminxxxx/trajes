<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Caja como libro inmutable (cada movimiento puede tener su reverso),
     * gastos que se anulan en vez de eliminarse, auditoría de ediciones y
     * bienes que se pueden vender.
     */
    public function up(): void
    {
        Schema::table('cash_movements', function (Blueprint $table) {
            // Era un enum cerrado: ahora hay más orígenes (aportes, retiros, compras...).
            $table->string('origin', 40)->change();
            // Movimiento que este anula (efecto inverso).
            $table->foreignId('reverses_id')->nullable()->after('reference_type')
                ->constrained('cash_movements')->restrictOnDelete();
        });

        Schema::table('operational_expenses', function (Blueprint $table) {
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable();
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            // En una edición: old_values = lo que había, new_values = lo que quedó.
            $table->json('new_values')->nullable()->after('old_values');
        });

        Schema::table('assets', function (Blueprint $table) {
            // Con precio de venta (IGV incluido) el bien se puede vender desde el panel de ventas.
            $table->decimal('sale_price', 12, 2)->nullable()->after('current_unit_value');
        });

        Schema::table('sale_asset_deliveries', function (Blueprint $table) {
            // Cobrado por la línea, IGV incluido. 0 = se entregó de regalo.
            $table->decimal('line_total', 12, 2)->default(0)->after('unit_cost');
        });
    }

    public function down(): void
    {
        Schema::table('sale_asset_deliveries', fn (Blueprint $table) => $table->dropColumn('line_total'));
        Schema::table('assets', fn (Blueprint $table) => $table->dropColumn('sale_price'));
        Schema::table('audit_logs', fn (Blueprint $table) => $table->dropColumn('new_values'));

        Schema::table('operational_expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['cancelled_at', 'cancellation_reason']);
        });

        Schema::table('cash_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reverses_id');
        });
    }
};
