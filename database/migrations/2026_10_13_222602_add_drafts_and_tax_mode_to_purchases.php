<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            // Borrador: se guarda lo avanzado sin tocar stock ni caja.
            $table->enum('status', ['draft', 'completed', 'cancelled'])->default('completed')->change();

            // true: los precios del detalle ya incluyen IGV (como vienen en el
            // comprobante). Las compras anteriores se registraron sin IGV.
            $table->boolean('prices_include_tax')->default(false)->after('has_invoice_pending');

            // Total impreso en el comprobante, para cuadrar lo registrado.
            $table->decimal('document_total', 10, 2)->nullable()->after('total');

            // Líneas del borrador (aún sin variante: talla/color pueden faltar).
            $table->json('draft_items')->nullable()->after('notes');
        });

        Schema::table('products', function (Blueprint $table) {
            // Precio referencial al que se compra; se propone al registrar una compra.
            $table->decimal('purchase_price', 10, 2)->default(0.00)->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('purchase_price');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn(['prices_include_tax', 'document_total', 'draft_items']);
            $table->enum('status', ['completed', 'cancelled'])->default('completed')->change();
        });
    }
};
