<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Antes, un comprobante de modo prueba (SUNAT beta) marcaba la venta como facturada y le ponía su
     * número. Un comprobante de prueba no tiene valor tributario: esas ventas vuelven a quedar
     * pendientes de facturar. Solo se tocan las que llevan el número de un comprobante de prueba y no
     * tienen ninguno de producción.
     */
    public function up(): void
    {
        $testDocuments = DB::table('sunat_documents')
            ->where('environment', 'beta')
            ->whereIn('status', ['aceptado', 'observado'])
            ->get(['sale_id', 'series', 'number']);

        foreach ($testDocuments as $document) {
            $hasProduction = DB::table('sunat_documents')
                ->where('sale_id', $document->sale_id)
                ->where(fn ($q) => $q->whereNull('environment')->orWhere('environment', '!=', 'beta'))
                ->whereIn('status', ['pendiente', 'aceptado', 'observado', 'error_envio', 'sin_conexion'])
                ->exists();

            if ($hasProduction) {
                continue;
            }

            DB::table('sales')
                ->where('id', $document->sale_id)
                ->where('document_number', "{$document->series}-{$document->number}")
                ->update(['has_invoice_pending' => true, 'document_number' => null, 'invoice_date' => null]);
        }
    }

    public function down(): void
    {
        // Irreversible a propósito: no hay que volver a marcar ventas como facturadas por un comprobante de prueba.
    }
};
