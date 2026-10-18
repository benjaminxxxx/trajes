<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Características del producto (tela, corte, cuero...) como TEXTO en el
 * producto. `attributes`/`attribute_values` pasan a ser solo sugerencias:
 * renombrarlas o borrarlas no cambia ningún producto ya registrado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('name');
        });

        // Categorías en las que se pide la característica (ninguna = todas).
        Schema::create('attribute_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['attribute_id', 'category_id']);
        });

        Schema::create('product_characteristics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name'); // Nombre al registrarlo (Tela, Corte...)
            $table->string('value'); // Satín, Sesgo...
            $table->timestamps();

            $table->unique(['product_id', 'name']);
        });

        // La ficha técnica anterior (pivote con valores) pasa a texto.
        $rows = DB::table('product_attribute_values')
            ->join('attribute_values', 'attribute_values.id', '=', 'product_attribute_values.attribute_value_id')
            ->join('attributes', 'attributes.id', '=', 'attribute_values.attribute_id')
            ->join('products', 'products.id', '=', 'product_attribute_values.product_id')
            ->orderBy('attribute_values.id')
            ->get(['products.account_id', 'product_attribute_values.product_id', 'attributes.id as attribute_id', 'attributes.name', 'attribute_values.value']);

        foreach ($rows->groupBy(fn ($row) => $row->product_id.'-'.$row->attribute_id) as $group) {
            $first = $group->first();

            DB::table('product_characteristics')->insert([
                'account_id' => $first->account_id,
                'product_id' => $first->product_id,
                'attribute_id' => $first->attribute_id,
                'name' => $first->name,
                'value' => $group->pluck('value')->implode(', '),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::dropIfExists('product_attribute_values');
    }

    public function down(): void
    {
        Schema::create('product_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['product_id', 'attribute_value_id'], 'product_attr_value_unique');
        });

        Schema::dropIfExists('product_characteristics');
        Schema::dropIfExists('attribute_category');

        Schema::table('attributes', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
