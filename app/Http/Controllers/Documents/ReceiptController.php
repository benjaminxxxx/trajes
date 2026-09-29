<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Models\Sale;
use App\Services\Documents\ReceiptService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Comprobantes PDF de ventas y alquileres: visor con opción de compartir,
 * el archivo para usuarios con sesión y un enlace público firmado (temporal)
 * para que el cliente lo abra desde WhatsApp.
 */
class ReceiptController extends Controller
{
    public function __construct(protected ReceiptService $receipts) {}

    public function show(string $type, int $id): View
    {
        $operation = $this->resolve($type, $id, authorize: true);

        return view('receipts.show', [
            'operation' => $operation,
            'type' => $type,
            'title' => ReceiptService::TYPES[$type][2],
            'pdfUrl' => route('receipts.file', [$type, $id]),
            'downloadUrl' => route('receipts.file', [$type, $id, 'descargar' => 1]),
            'fileName' => $this->receipts->fileName($operation),
            'shareText' => $this->receipts->shareText($operation),
            'whatsappWebUrl' => $this->receipts->whatsappUrl($operation, web: true),
            'whatsappMobileUrl' => $this->receipts->whatsappUrl($operation, web: false),
            'backUrl' => $type === 'venta' ? route('sale.sales.index') : route('rental.rentals.index'),
            'newUrl' => $type === 'venta' ? route('sale.sales.create') : route('rental.rentals.create'),
            'newLabel' => $type === 'venta' ? 'Nueva venta' : 'Nuevo alquiler',
        ]);
    }

    public function file(Request $request, string $type, int $id): Response
    {
        $operation = $this->resolve($type, $id, authorize: true);

        return $this->pdfResponse($operation, download: $request->boolean('descargar'));
    }

    /**
     * Enlace público (firmado y con vencimiento) que recibe el cliente.
     */
    public function public(string $type, int $id): Response
    {
        return $this->pdfResponse($this->resolve($type, $id, authorize: false));
    }

    protected function resolve(string $type, int $id, bool $authorize): Sale|Rental
    {
        abort_unless(isset(ReceiptService::TYPES[$type]), 404);

        if ($authorize) {
            Gate::authorize($type === 'venta' ? 'ventas.ver' : 'alquileres.ver');
        }

        [$model] = ReceiptService::TYPES[$type];

        return $model::findOrFail($id);
    }

    protected function pdfResponse(Sale|Rental $operation, bool $download = false): Response
    {
        $path = $this->receipts->path($operation);
        $name = $this->receipts->fileName($operation);
        $disk = Storage::disk(ReceiptService::DISK);

        return $download
            ? $disk->download($path, $name)
            : $disk->response($path, $name, [
                'Content-Type' => 'application/pdf',
                'Cache-Control' => 'private, no-store, max-age=0',
            ]);
    }
}
