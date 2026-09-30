<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Total de la línea CON IGV tal como se cobró (monto fijado por el vendedor).
     *
     * unit_price y subtotal se guardan sin IGV y redondeados: desde ahí no siempre se puede
     * volver al monto cobrado (ej. 0.10 → 0.08 sin IGV → 0.09). El comprobante electrónico usa este valor.
     * Queda null en ventas anteriores; para ellas se reconstruye desde el subtotal.
     */
    public function up(): void
    {
        Schema::table('sale_details', function (Blueprint $table) {
            $table->decimal('line_total', 12, 2)->nullable()->after('subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('sale_details', function (Blueprint $table) {
            $table->dropColumn('line_total');
        });
    }
};
