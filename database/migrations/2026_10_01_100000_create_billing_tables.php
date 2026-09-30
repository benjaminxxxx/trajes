<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Conexión de cada negocio con el servicio de facturación (un token por negocio).
        Schema::create('billing_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('api_token')->nullable(); // cifrado con APP_KEY
            $table->string('factura_series', 4)->nullable();
            $table->string('boleta_series', 4)->nullable();

            // Última verificación contra la API (GET /yo).
            $table->string('company_ruc', 11)->nullable();
            $table->string('company_name')->nullable();
            $table->string('environment', 12)->nullable(); // beta | produccion
            $table->string('status', 20)->default('sin_configurar'); // conectado | token_invalido | sin_conexion | sin_configurar
            $table->string('status_message')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
        });

        // Comprobantes electrónicos emitidos (o intentados) para cada venta.
        Schema::create('sunat_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Idempotencia: reintentar con la misma referencia nunca duplica el comprobante.
            $table->string('reference', 100);
            $table->uuid('remote_id')->nullable(); // id del comprobante en el servicio de facturación
            $table->string('environment', 12)->nullable();

            $table->string('document_type', 10); // factura | boleta
            $table->string('series', 4);
            $table->unsignedInteger('number')->nullable();
            $table->date('issue_date');
            $table->string('customer_document_type', 1)->nullable();
            $table->string('customer_document_number', 15)->nullable();
            $table->string('customer_name')->nullable();
            $table->decimal('total', 12, 2);

            // pendiente | aceptado | observado | rechazado | error_envio | sin_conexion
            $table->string('status', 20)->index();
            $table->string('sunat_code', 10)->nullable();
            $table->text('sunat_description')->nullable();
            $table->json('sunat_notes')->nullable();
            $table->string('hash', 100)->nullable();
            $table->unsignedSmallInteger('attempts')->default(1);
            $table->json('request_payload');

            // La venta se anuló con este comprobante válido: falta emitir la nota de crédito.
            $table->boolean('credit_note_required')->default(false)->index();
            $table->timestamps();

            $table->unique(['account_id', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sunat_documents');
        Schema::dropIfExists('billing_settings');
    }
};
