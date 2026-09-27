<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // TALLAS (Dedicada por su orden lógico y tipo)
        Schema::create('sizes', function (Blueprint $table) {
            $table->id();
            $table->string('name');                      // S, M, L, 38, 40
            $table->string('type')->default('estandar'); // estandar, nino, calzado
            $table->unsignedInteger('sort_order')->default(0); // Para ordenar S < M < L < XL
            $table->timestamps();
        });

        // COLORES (Dedicada por el código HEX visual)
        Schema::create('colors', function (Blueprint $table) {
            $table->id();
            $table->string('name');                     // Azul Noche, Rojo Vino
            $table->string('hex_code', 7)->nullable();  // #000080
            $table->timestamps();
        });

        Schema::create('category_size', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('size_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['category_id', 'size_id']);
        });
        Schema::create('category_color', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('color_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
 
            $table->unique(['category_id', 'color_id']);
        });

        // MARCAS
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        // ATRIBUTOS SECUNDARIOS / FICHA TÉCNICA (Especificaciones sin stock)
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Elasticidad, Cuidado, Procedencia, Tipo de Tela
            $table->timestamps();
        });

        Schema::create('attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->string('value'); // 100% Algodón, Lavar en seco, China
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attributes');
        Schema::dropIfExists('attribute_values');
        Schema::dropIfExists('colors');
        Schema::dropIfExists('sizes');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('category_size');
        Schema::dropIfExists('category_color');

    }
};
