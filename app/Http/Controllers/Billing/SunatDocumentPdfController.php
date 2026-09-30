<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\SunatDocument;
use App\Services\Billing\BillingException;
use App\Services\Billing\BillingService;
use Symfony\Component\HttpFoundation\Response;

/**
 * PDF oficial (A4 o ticket 80 mm) del comprobante electrónico. Lo genera el servicio de
 * facturación; aquí solo se pide con el token del negocio y se muestra en el navegador.
 */
class SunatDocumentPdfController extends Controller
{
    public function __invoke(BillingService $billing, SunatDocument $sunatDocument, string $format): Response
    {
        abort_if($sunatDocument->remote_id === null, 404, 'Este comprobante aún no fue registrado en el servicio de facturación.');

        try {
            $pdf = $billing->client()->pdf((string) $sunatDocument->remote_id, $format);
        } catch (BillingException $e) {
            abort(503, $e->getMessage());
        }

        $name = "{$sunatDocument->typeLabel()}-{$sunatDocument->fullNumber()}".($format === 'ticket' ? '-ticket' : '').'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$name.'"',
        ]);
    }
}
