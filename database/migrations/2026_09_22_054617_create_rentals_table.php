<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. ALQUILERES (Contrato / Encabezado)
        Schema::create('rentals', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
 
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
 
            $table->dateTime('pickup_date');
            $table->dateTime('expected_return_date');
            $table->dateTime('actual_return_date')->nullable();
 
            $table->decimal('rental_total', 10, 2)->default(0.00);
            $table->decimal('deposit_amount', 10, 2)->default(0.00);
            $table->decimal('penalty_total', 10, 2)->default(0.00);
 
            $table->enum('status', ['reserved', 'out', 'returned', 'converted_to_sale', 'cancelled'])
                ->default('out')
                ->index();
 
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. DETALLE DE ALQUILER
        Schema::create('rental_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained('rentals')->cascadeOnDelete();
            $table->foreignId('physical_product_id')->constrained('physical_products')->restrictOnDelete();

            $table->decimal('price', 10, 2);
            $table->enum('return_condition', ['good', 'dirty', 'damaged', 'lost'])->nullable();
            $table->decimal('damage_fee', 10, 2)->default(0.00);
            $table->text('damage_note')->nullable();

            $table->timestamps();
        });

        // 3. EVIDENCIAS FOTOGRÁFICAS
        Schema::create('rental_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained('rentals')->cascadeOnDelete();
            $table->string('file_path');
            $table->enum('type', ['dni', 'guarantee_receipt', 'delivery_condition', 'return_damage'])
                ->default('delivery_condition');
            $table->timestamps();
        });

        // 4. VENTAS (Encabezado)
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();

            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('document_type', ['nota_venta', 'boleta', 'factura'])->default('nota_venta');
            $table->string('document_number', 50)->nullable();
            $table->dateTime('invoice_date')->nullable();
            $table->boolean('has_invoice_pending')->default(false);

            $table->dateTime('sale_date');
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tax', 10, 2)->default(0.00);
            $table->decimal('total', 10, 2);

            $table->enum('status', ['completed', 'cancelled'])->default('completed')->index();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        // 5. DETALLE DE VENTAS
        Schema::create('sale_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('physical_product_id')->nullable()->constrained('physical_products')->nullOnDelete();

            $table->integer('quantity')->default(1);
            $table->decimal('unit_cost', 10, 2);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('subtotal', 10, 2);

            $table->timestamps();
        });

        // 6. MANTENIMIENTO DE PRENDAS
        Schema::create('item_maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('physical_product_id')->constrained('physical_products')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rental_id')->nullable()->constrained('rentals')->nullOnDelete();

            $table->enum('type', ['laundromat', 'tailor_repair', 'ironing', 'other'])->default('laundromat');
            $table->decimal('cost', 10, 2);
            $table->string('provider_name')->nullable();
            $table->text('description')->nullable();

            $table->timestamps();
        });

        // 7. SERVICIOS ADICIONALES (Encabezado)
        Schema::create('extra_services', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->dateTime('service_date');
            $table->decimal('total', 10, 2);
            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('completed');
            $table->text('notes')->nullable();

            $table->timestamps();
        });

        // 8. DETALLE DE SERVICIOS ADICIONALES
        Schema::create('extra_service_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('extra_service_id')->constrained('extra_services')->cascadeOnDelete();

            $table->enum('service_type', ['ironing', 'tailoring_repair', 'shoe_decoration', 'other']);
            $table->string('description');
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('subtotal', 10, 2);

            $table->timestamps();
        });

        // 9. GASTOS OPERATIVOS
        Schema::create('operational_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('category', [
                'store_rent',
                'payroll',
                'utilities',
                'supplies',
                'maintenance',
                'other'
            ]);

            $table->string('concept');
            $table->decimal('amount', 10, 2);
            $table->dateTime('expense_date');
            $table->text('notes')->nullable();

            $table->timestamps();
        });

        // 10. MOVIMIENTOS DE CAJA (Kardex Financiero - Creado al final para soportar referencias)
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('type', ['income', 'expense']);

            $table->enum('origin', [
                'rental_payment',
                'sale_payment',
                'extra_service_payment',
                'penalty_fee',
                'operational_expense',
                'purchase_expense',
                'other'
            ]);

            $table->decimal('amount', 10, 2);
            $table->string('payment_method')->default('cash');

            $table->nullableMorphs('reference');

            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Eliminación estrictamente inversa
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('operational_expenses');
        Schema::dropIfExists('extra_service_details');
        Schema::dropIfExists('extra_services');
        Schema::dropIfExists('item_maintenances');
        Schema::dropIfExists('sale_details');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('rental_media');
        Schema::dropIfExists('rental_details');
        Schema::dropIfExists('rentals');
    }
};