<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etiquetas manuales del producto (Quinceañera, Señora, Boda...): lo que no
 * sale de la categoría, género, tallas, colores ni características, que ya
 * se buscan solas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->timestamps();

            $table->unique(['product_id', 'name']);
            $table->index(['account_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_tags');
    }
};
