<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();

            $table->enum('type', ['individual', 'company'])->default('individual');

            $table->string('document_type', 20);
            $table->string('document_number', 30);

            // Persona natural
            $table->string('names')->nullable();
            $table->string('last_name')->nullable();
            $table->date('birth_date')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();

            // Persona juridica
            $table->string('company_name')->nullable();

            // Cacheado: nombre a mostrar, calculado al crear/actualizar
            $table->string('display_name');

            // Contacto
            $table->string('mobile', 30)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();

            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['document_type', 'document_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
