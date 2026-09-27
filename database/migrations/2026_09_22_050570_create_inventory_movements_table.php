<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();

            // Apunta a la variante (INDISPENSABLE para calcular/verificar stock)
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();

            // Opcional: Para auditoría puntual cuando el movimiento afectó a una etiqueta QR/física específica
            $table->foreignId('physical_product_id')->nullable()->constrained('physical_products')->nullOnDelete();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('type', ['in', 'out']); // 'in' = Entrada (+), 'out' = Salida (-)
            $table->string('concept'); // purchase, sale, rental, lost, stolen, adjustment_in, adjustment_out

            $table->integer('quantity')->default(1);
            $table->decimal('unit_cost', 10, 2)->default(0.00);
            $table->decimal('total_cost', 10, 2)->default(0.00);

            // Relación polimórfica para saber qué documento originó esto (PurchaseDetail, Sale, Rental, StockAdjustment)
            $table->nullableMorphs('reference');

            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};