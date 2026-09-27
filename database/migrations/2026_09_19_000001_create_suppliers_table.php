<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();

            $table->string('code', 30)->unique(); // PROV-000001, generado por CreateSupplierAction

            $table->enum('type', ['individual', 'company'])->default('individual');

            $table->string('document_type', 20);   // DNI, RUC, CE, Pasaporte...
            $table->string('document_number', 30);

            // Persona natural
            $table->string('names')->nullable();
            $table->string('paternal_last_name')->nullable();
            $table->string('maternal_last_name')->nullable();

            // Persona juridica
            $table->string('company_name')->nullable();

            // Cacheado: nombre a mostrar, calculado al crear/actualizar
            $table->string('display_name');

            // Contacto
            $table->string('mobile', 30)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();

            // Datos bancarios (para pagos al proveedor)
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('cci')->nullable();

            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['document_type', 'document_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
