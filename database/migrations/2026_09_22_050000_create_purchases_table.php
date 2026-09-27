<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // Comprobante
            $table->enum('document_type', ['nota_venta', 'boleta', 'factura'])->default('nota_venta');
            $table->string('document_number')->nullable(); // Ej: F001-0000123
            $table->boolean('has_invoice_pending')->default(false); // Flag si prometieron factura

            $table->date('issue_date');

            // Financiero (bookkeeping del comprobante; el costo REAL de
            // inventario se calcula aparte por unidad, ver PurchaseCostCalculator)
            $table->decimal('subtotal', 10, 2)->default(0.00);
            $table->decimal('tax', 10, 2)->default(0.00);
            $table->decimal('total', 10, 2)->default(0.00);

            $table->enum('status', ['completed', 'cancelled'])->default('completed');
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes(); // faltaba: PurchaseList usa showTrashed/restore
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};