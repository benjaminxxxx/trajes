<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bienes del negocio (mobiliario, maniquíes, ganchos, portaternos...) para
     * el valorizado de la empresa, y reglas de desgaste de prendas por alquileres.
     */
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 30)->default('otro'); // mobiliario, exhibicion, empaque, accesorio, herramienta, otro
            $table->unsignedInteger('quantity')->default(0);
            $table->decimal('unit_cost', 12, 4)->default(0);          // costo de compra por unidad
            $table->decimal('current_unit_value', 12, 4)->default(0); // valor actual por unidad
            // Consumible: se gasta (ej. portaternos). Si además se entrega con la
            // venta, el formulario de venta pregunta si se entrega.
            $table->boolean('is_consumable')->default(false);
            $table->boolean('delivers_with_sale')->default(false);
            $table->string('status', 20)->default('active'); // active, discharged
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Categorías de producto con las que se entrega el bien (vacío = todas).
        Schema::create('asset_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->unique(['asset_id', 'category_id']);
        });

        Schema::create('asset_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20); // initial, in, discharge, delivery, delivery_return, revalue
            $table->integer('quantity')->default(0);
            $table->decimal('unit_cost', 12, 4)->default(0);
            $table->nullableMorphs('reference');
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // Consumibles entregados en cada venta (su costo resta del margen).
        Schema::create('sale_asset_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_cost', 12, 4)->default(0);
            $table->timestamps();
        });

        // Desgaste de prendas físicas: desde N alquileres, su valor baja X %.
        Schema::create('depreciation_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('min_rentals')->unique();
            $table->decimal('percent', 5, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('depreciation_rules');
        Schema::dropIfExists('sale_asset_deliveries');
        Schema::dropIfExists('asset_movements');
        Schema::dropIfExists('asset_category');
        Schema::dropIfExists('assets');
    }
};
