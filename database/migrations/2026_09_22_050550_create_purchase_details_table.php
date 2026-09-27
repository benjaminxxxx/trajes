<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('purchase_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();

            // Apunta directamente a la variante (la variante ya sabe su producto, talla y color)
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();

            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2); // Precio pactado de compra por unidad
            $table->decimal('discount_percent', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_details');
    }
};