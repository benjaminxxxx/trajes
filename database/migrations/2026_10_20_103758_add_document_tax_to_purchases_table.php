<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            // IGV impreso en el comprobante: corrige el redondeo para que el
            // crédito fiscal coincida al céntimo con la factura.
            $table->decimal('document_tax', 10, 2)->nullable()->after('document_total');
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn('document_tax');
        });
    }
};
