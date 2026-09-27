<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('physical_products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique(); // Ej: TER-KING-001-00007

            // Apunta a la variante (que provee la Talla y el Color)
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();

            // Apunta a la compra origen (para costo, fecha de ingreso y proveedor)
            $table->foreignId('purchase_detail_id')->nullable()->constrained('purchase_details')->nullOnDelete();

            $table->decimal('purchase_cost', 10, 2)->nullable();
            $table->decimal('suggested_sale_price', 10, 2)->nullable();
            $table->decimal('final_sale_price', 10, 2)->nullable();
            $table->decimal('rental_price', 10, 2)->nullable();

            $table->enum('status', [
                'available',
                'rented',
                'sold',
                'maintenance',
                'lost',
                'stolen',
                'not_delivered',
                'discharged',
            ])->default('available')->index();

            $table->text('status_note')->nullable();
            $table->date('sold_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('physical_products');
    }
};