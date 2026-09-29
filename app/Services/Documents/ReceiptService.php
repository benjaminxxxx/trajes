<?php

namespace App\Services\Documents;

use App\Models\Rental;
use App\Models\Sale;
use App\Services\Sale\SaleService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Comprobantes en PDF de ventas y alquileres (no tributarios).
 *
 * El PDF se guarda en el disco privado y se regenera solo si la operación
 * cambió después de generarlo (ej. se editó el cliente o se anuló).
 * Los precios se muestran CON IGV y sin desglosarlo: es lo que pagó el cliente.
 */
class ReceiptService
{
    public const DISK = 'local';

    /**
     * Tipos de comprobante: segmento de URL => [modelo, carpeta, título].
     */
    public const TYPES = [
        'venta' => [Sale::class, 'receipts/ventas', 'Comprobante de venta'],
        'alquiler' => [Rental::class, 'receipts/alquileres', 'Comprobante de alquiler'],
    ];

    /**
     * Ruta del PDF en el disco privado, generándolo si falta o está desactualizado.
     */
    public function path(Sale|Rental $operation): string
    {
        [, $folder] = self::TYPES[$this->type($operation)];
        $path = "{$folder}/{$operation->code}.pdf";
        $disk = Storage::disk(self::DISK);

        $isStale = ! $disk->exists($path)
            || $disk->lastModified($path) < $operation->updated_at?->getTimestamp();

        if ($isStale) {
            $disk->put($path, $this->render($operation));
        }

        return $path;
    }

    public function render(Sale|Rental $operation): string
    {
        $data = $operation instanceof Sale ? $this->saleData($operation) : $this->rentalData($operation);

        return Pdf::loadView('pdf.receipt', [
            ...$data,
            'business' => config('business'),
            'logo' => public_path('brand/kipusoft-alquiler-horizontal.png'),
        ])
            ->setPaper('a5')
            // Incrusta solo los caracteres usados de la fuente: el PDF pasa de ~900 KB a unas decenas de KB.
            ->setOption('isFontSubsettingEnabled', true)
            ->output();
    }

    public function type(Model $operation): string
    {
        return $operation instanceof Sale ? 'venta' : 'alquiler';
    }

    public function fileName(Sale|Rental $operation): string
    {
        return "comprobante-{$operation->code}.pdf";
    }

    /**
     * Enlace público, firmado y temporal al PDF, para enviarlo por WhatsApp.
     */
    public function publicUrl(Sale|Rental $operation): string
    {
        return URL::temporarySignedRoute(
            'receipts.public',
            now()->addDays(config('business.receipt_link_days', 30)),
            ['type' => $this->type($operation), 'id' => $operation->id],
        );
    }

    /**
     * Mensaje para compartir: saludo, tipo de comprobante y enlace al PDF.
     */
    public function shareText(Sale|Rental $operation): string
    {
        $client = $operation->client;
        $title = mb_strtolower(self::TYPES[$this->type($operation)][2]);
        $greeting = $client ? "Hola {$client->display_name}" : 'Hola';

        return "{$greeting}, te enviamos tu {$title} {$operation->code} de ".config('business.name').": {$this->publicUrl($operation)}";
    }

    /**
     * Enlace de WhatsApp con el mensaje. `web` abre WhatsApp Web (escritorio);
     * si no, wa.me (celular sin opción de compartir archivos). Si el cliente
     * tiene celular registrado, abre directamente su chat.
     */
    public function whatsappUrl(Sale|Rental $operation, bool $web = true): string
    {
        $phone = $this->whatsappPhone($operation->client?->mobile);
        $text = $this->shareText($operation);

        return $web
            ? 'https://web.whatsapp.com/send?'.http_build_query(array_filter(['phone' => $phone, 'text' => $text]))
            : 'https://wa.me/'.($phone ?? '').'?'.http_build_query(['text' => $text]);
    }

    /**
     * Celular peruano (9 dígitos que empiezan con 9) → formato internacional
     * 51XXXXXXXXX. Un teléfono fijo no sirve para WhatsApp: devuelve null.
     */
    public function whatsappPhone(?string $mobile): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $mobile);

        return match (true) {
            preg_match('/^9\d{8}$/', $digits) === 1 => '51'.$digits,
            preg_match('/^519\d{8}$/', $digits) === 1 => $digits,
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function saleData(Sale $sale): array
    {
        $sale->loadMissing([
            'client', 'user:id,name',
            'details.variant.product:id,name,code', 'details.variant.size:id,name', 'details.variant.color:id,name',
            'details.physicalProduct:id,code',
        ]);

        // Montos con IGV por línea; la última absorbe el redondeo para que sumen el total cobrado.
        $lines = $sale->details->map(fn ($detail) => [
            'description' => $detail->variant?->product?->name,
            'detail' => collect([$detail->variant?->label(), $detail->physicalProduct?->code])->filter()->join(' · '),
            'quantity' => $detail->quantity,
            'amount' => round((float) $detail->subtotal * (1 + SaleService::TAX_RATE), 2),
        ])->values()->all();

        if ($lines !== []) {
            $difference = round((float) $sale->total - array_sum(array_column($lines, 'amount')), 2);
            $lines[count($lines) - 1]['amount'] += $difference;
        }

        foreach ($lines as &$line) {
            $line['unit_price'] = round($line['amount'] / max(1, $line['quantity']), 2);
        }

        return [
            'title' => self::TYPES['venta'][2],
            'code' => $sale->code,
            'date' => $sale->sale_date,
            'client' => $sale->client,
            'seller' => $sale->user?->name,
            'lines' => $lines,
            'total' => (float) $sale->total,
            'is_cancelled' => $sale->isCancelled(),
            'document' => $sale->document_number
                ? (Sale::DOCUMENT_TYPES[$sale->document_type] ?? $sale->document_type).' '.$sale->document_number
                : null,
            'rental' => null,
            'notes' => $sale->notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function rentalData(Rental $rental): array
    {
        $rental->loadMissing([
            'client', 'user:id,name',
            'details.physicalProduct.variant.product:id,name,code',
            'details.physicalProduct.variant.size:id,name', 'details.physicalProduct.variant.color:id,name',
        ]);

        $lines = $rental->details->map(fn ($detail) => [
            'description' => $detail->physicalProduct?->variant?->product?->name,
            'detail' => collect([$detail->physicalProduct?->variant?->label(), $detail->physicalProduct?->code])->filter()->join(' · '),
            'quantity' => 1,
            'unit_price' => (float) $detail->price,
            'amount' => (float) $detail->price,
        ])->values()->all();

        return [
            'title' => self::TYPES['alquiler'][2],
            'code' => $rental->code,
            'date' => $rental->created_at,
            'client' => $rental->client,
            'seller' => $rental->user?->name,
            'lines' => $lines,
            'total' => (float) $rental->rental_total,
            'is_cancelled' => $rental->isCancelled(),
            'document' => null,
            'rental' => [
                'pickup' => $rental->pickup_date,
                'expected_return' => $rental->expected_return_date,
                'actual_return' => $rental->actual_return_date,
                'deposit' => (float) $rental->deposit_amount,
                'penalty' => (float) $rental->penalty_total,
            ],
            'notes' => $rental->notes,
        ];
    }
}
