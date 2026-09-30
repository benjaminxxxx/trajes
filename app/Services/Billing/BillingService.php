<?php

namespace App\Services\Billing;

use App\Models\BillingSetting;
use App\Models\Client;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\SunatDocument;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Facturación electrónica de ventas a través del servicio central (Kipusoft Facturación).
 *
 * - Arma el comprobante desde la venta: el total es el cobrado y el detalle sale de sus productos.
 * - Guarda cada respuesta en sunat_documents: aceptado, rechazado, pendiente en SUNAT o sin respuesta.
 * - Reintentar nunca duplica: cada intento lleva una referencia única que el servicio usa como idempotencia.
 */
class BillingService
{
    public const TAX_RATE = '1.18';

    /** SUNAT no acepta comprobantes con más días de antigüedad. */
    public const MAX_DAYS_BACK = 3;

    public const BOLETA_ANONYMOUS_LIMIT = 700;

    /** Documento del cliente en el sistema → catálogo 06 de SUNAT. */
    public const CUSTOMER_DOCUMENT_TYPES = [
        'DNI' => '1',
        'RUC' => '6',
        'CE' => '4',
        'PASAPORTE' => '7',
    ];

    public function settings(): BillingSetting
    {
        return BillingSetting::current();
    }

    public function isEnabled(): bool
    {
        return $this->settings()->isReady();
    }

    public function client(?string $token = null): FacturacionClient
    {
        $token ??= $this->settings()->api_token;

        if (blank($token)) {
            throw new BillingException(BillingException::INVALID_TOKEN, 'Configure el token de facturación en Facturación → Configuración.');
        }

        return new FacturacionClient((string) $token);
    }

    /**
     * Verifica el token contra el servicio (GET /yo) y guarda el resultado.
     *
     * @return list<array{tipo_comprobante: string, serie: string, ultimo_correlativo: int}> series disponibles
     */
    public function verify(BillingSetting $settings, ?string $newToken = null): array
    {
        $token = filled($newToken) ? trim((string) $newToken) : $settings->api_token;

        try {
            $me = $this->client($token)->me();
        } catch (BillingException $e) {
            $settings->update([
                'status' => match ($e->kind) {
                    BillingException::INVALID_TOKEN => BillingSetting::STATUS_INVALID_TOKEN,
                    BillingException::OFFLINE => BillingSetting::STATUS_OFFLINE,
                    default => $settings->status,
                },
                'status_message' => Str::limit($e->getMessage(), 250),
                'checked_at' => now(),
            ]);

            throw $e;
        }

        $series = array_values((array) ($me['series'] ?? []));
        $available = fn (string $type) => collect($series)->where('tipo_comprobante', $type)->pluck('serie');

        $settings->update([
            'api_token' => $token,
            'company_ruc' => $me['empresa']['ruc'] ?? null,
            'company_name' => $me['empresa']['razon_social'] ?? null,
            'environment' => $me['empresa']['entorno'] ?? null,
            'status' => BillingSetting::STATUS_CONNECTED,
            'status_message' => null,
            'checked_at' => now(),
            // Conserva la serie elegida si sigue existiendo; si no, propone la primera.
            'factura_series' => $available('01')->contains($settings->factura_series) ? $settings->factura_series : $available('01')->first(),
            'boleta_series' => $available('03')->contains($settings->boleta_series) ? $settings->boleta_series : $available('03')->first(),
        ]);

        return $series;
    }

    /**
     * Problemas que impiden facturar la venta (vacío = se puede).
     *
     * @return list<string>
     */
    public function problems(Sale $sale, string $documentType, string $issueDate): array
    {
        $problems = [];
        $settings = $this->settings();

        if ($sale->isCancelled()) {
            $problems[] = 'La venta está anulada.';
        }

        if ($sale->liveSunatDocument()->exists()) {
            $problems[] = 'La venta ya tiene un comprobante electrónico vigente o pendiente de respuesta.';
        }

        if (! $settings->seriesFor($documentType)) {
            $problems[] = 'No hay serie de '.($documentType === 'factura' ? 'factura' : 'boleta').' configurada en Facturación → Configuración.';
        }

        $date = CarbonImmutable::parse($issueDate, 'America/Lima')->startOfDay();
        $today = CarbonImmutable::now('America/Lima')->startOfDay();
        if ($date->gt($today)) {
            $problems[] = 'La fecha de emisión no puede ser futura.';
        } elseif ($date->lt($today->subDays(self::MAX_DAYS_BACK))) {
            $problems[] = 'SUNAT solo acepta comprobantes con fecha de hasta '.self::MAX_DAYS_BACK.' días atrás.';
        }

        $client = $sale->client;
        $sunatDocType = $client ? (self::CUSTOMER_DOCUMENT_TYPES[strtoupper((string) $client->document_type)] ?? null) : null;

        if ($documentType === 'factura' && $sunatDocType !== '6') {
            $problems[] = 'La factura requiere un cliente con RUC. Asigne el cliente a la venta o emita una boleta.';
        }

        if ($documentType === 'boleta' && ! $sunatDocType && (float) $sale->total > self::BOLETA_ANONYMOUS_LIMIT) {
            $problems[] = 'Las boletas mayores a S/ '.self::BOLETA_ANONYMOUS_LIMIT.' requieren un cliente identificado (DNI, RUC, CE o pasaporte).';
        }

        return $problems;
    }

    /**
     * Líneas del comprobante a partir del detalle de la venta, con el importe CON IGV de cada una.
     *
     * Se envía el importe (monto fijado) y el servicio de facturación calcula hacia atrás el valor
     * sin IGV, el IGV y el precio unitario: el comprobante suma exactamente lo cobrado, sin
     * "monto de redondeo".
     *
     * - Ventas nuevas: sale_details.line_total guarda el importe cobrado; se usa tal cual.
     * - Ventas anteriores (sin line_total): se reconstruye desde el subtotal sin IGV. En ~15 % de los
     *   montos eso da 1 céntimo de diferencia (ej. 0.10 → 0.08 → 0.09); la diferencia con el total de
     *   la venta se absorbe en la línea reconstruida de mayor importe.
     *
     * @return list<array{codigo: ?string, descripcion: string, unidad: string, cantidad: int, precio_unitario: string, importe: string}>
     */
    public function lines(Sale $sale): array
    {
        $details = SaleDetail::where('sale_id', $sale->id)->with(['variant.product', 'variant.size', 'variant.color'])->orderBy('id')->get();

        $cents = [];
        $reconstructed = [];
        foreach ($details->values() as $i => $detail) {
            if ($detail->line_total !== null) {
                $cents[$i] = (int) round((float) $detail->line_total * 100);
            } else {
                $cents[$i] = (int) round(round((float) $detail->subtotal * 100) * (float) self::TAX_RATE);
                $reconstructed[$i] = $cents[$i];
            }
        }

        $difference = (int) round((float) $sale->total * 100) - array_sum($cents);
        if ($difference !== 0 && $reconstructed !== []) {
            $largest = array_keys($reconstructed, max($reconstructed))[0];
            $cents[$largest] += $difference;
        }

        return array_values($details->values()->map(function (SaleDetail $detail, int $i) use ($cents) {
            $quantity = max(1, (int) $detail->quantity);
            $amount = bcdiv((string) $cents[$i], '100', 2);
            $product = $detail->variant?->product;

            $description = collect([
                $product->name ?? 'Producto',
                $detail->variant?->size?->name ? 'Talla '.$detail->variant->size->name : null,
                $detail->variant?->color?->name,
            ])->filter()->implode(' - ');

            $code = $product?->code ? preg_replace('/[^A-Za-z0-9._\-\/]/', '', (string) $product->code) : null;

            return [
                'codigo' => $code ? Str::limit($code, 30, '') : null,
                'descripcion' => Str::limit($description, 500, ''),
                'unidad' => 'NIU',
                'cantidad' => $quantity,
                // Con 10 decimales, cantidad × precio vuelve exactamente al importe de la línea.
                'precio_unitario' => rtrim(rtrim(bcdiv($amount, (string) $quantity, 10), '0'), '.'),
                'importe' => $amount,
            ];
        })->all());
    }

    /**
     * @return array{tipo_documento: string, numero_documento: string, razon_social: string, direccion: ?string, email: ?string}|null
     */
    public function customer(Sale $sale): ?array
    {
        /** @var Client|null $client */
        $client = $sale->client;
        $type = $client ? (self::CUSTOMER_DOCUMENT_TYPES[strtoupper((string) $client->document_type)] ?? null) : null;

        if (! $client || ! $type || blank($client->document_number)) {
            return null; // Boleta a "CLIENTES VARIOS".
        }

        return [
            'tipo_documento' => $type,
            'numero_documento' => trim((string) $client->document_number),
            'razon_social' => Str::limit(trim((string) ($client->display_name ?: $client->company_name)), 500, ''),
            'direccion' => $client->address ? Str::limit((string) $client->address, 250, '') : null,
            'email' => filter_var($client->email, FILTER_VALIDATE_EMAIL) ? $client->email : null,
        ];
    }

    /**
     * Envía la venta al servicio de facturación y guarda lo que responda.
     *
     * @throws BillingException token inválido, datos rechazados o error del servidor (no se guarda nada).
     *                          Sin conexión se guarda como "sin respuesta" para reintentar después.
     */
    public function invoice(Sale $sale, string $documentType, string $issueDate): SunatDocument
    {
        $problems = $this->problems($sale, $documentType, $issueDate);
        if ($problems !== []) {
            throw new BillingException(BillingException::VALIDATION, $problems[0], ['venta' => $problems]);
        }

        $settings = $this->settings();
        $series = (string) $settings->seriesFor($documentType);
        $customer = $this->customer($sale);
        $reference = "venta-{$sale->id}-".($sale->sunatDocuments()->count() + 1);

        $date = CarbonImmutable::parse($issueDate, 'America/Lima');
        $today = CarbonImmutable::now('America/Lima');
        $issuedAt = $date->isSameDay($today) ? $today : $date->setTime(12, 0);

        $payload = array_filter([
            'referencia_externa' => $reference,
            'tipo_comprobante' => $documentType === 'factura' ? '01' : '03',
            'serie' => $series,
            'fecha_emision' => $issuedAt->toIso8601String(),
            'cliente' => $customer,
            'observacion' => "Venta {$sale->code}",
            // Se envía el importe fijado de cada línea: el servicio deriva precio, valor e IGV.
            'items' => array_map(fn (array $line) => array_filter([
                'codigo' => $line['codigo'],
                'descripcion' => $line['descripcion'],
                'unidad' => $line['unidad'],
                'cantidad' => $line['cantidad'],
                'importe' => $line['importe'],
            ], fn ($v) => $v !== null), $this->lines($sale)),
        ], fn ($v) => $v !== null);

        $document = new SunatDocument([
            'sale_id' => $sale->id,
            'user_id' => Auth::id(),
            'reference' => $reference,
            // Entorno esperado; la respuesta del servicio lo confirma. Sin él, un intento sin respuesta
            // no sabría si es de prueba (no bloquea) o de producción (sí bloquea).
            'environment' => $settings->environment,
            'document_type' => $documentType,
            'series' => $series,
            'issue_date' => $issuedAt->toDateString(),
            'customer_document_type' => $customer['tipo_documento'] ?? '-',
            'customer_document_number' => $customer['numero_documento'] ?? '-',
            'customer_name' => $customer['razon_social'] ?? 'CLIENTES VARIOS',
            'total' => $sale->total,
            'request_payload' => $payload,
            'attempts' => 1,
        ]);

        return $this->send($document, fn (FacturacionClient $api) => $api->emit($payload));
    }

    /**
     * Consulta o reintenta un comprobante sin respuesta definitiva:
     * - sin respuesta del servicio: reenvía el MISMO pedido (misma referencia, no duplica);
     * - pendiente en SUNAT: consulta el estado y, si sigue pendiente, pide al servicio reenviarlo.
     */
    public function refresh(SunatDocument $document): SunatDocument
    {
        if (! $document->isAwaiting()) {
            return $document;
        }

        $document->attempts++;

        if ($document->status === SunatDocument::STATUS_OFFLINE || ! $document->remote_id) {
            return $this->send($document, fn (FacturacionClient $api) => $api->emit($document->request_payload));
        }

        return $this->send($document, function (FacturacionClient $api) use ($document) {
            $data = $api->show((string) $document->remote_id);

            if (in_array($data['estado'] ?? null, [SunatDocument::STATUS_PENDING, SunatDocument::STATUS_SEND_ERROR], true)) {
                try {
                    $data = $api->resend((string) $document->remote_id);
                } catch (BillingException $e) {
                    // 422 = ya tenía respuesta definitiva (otro reintento se adelantó): vale lo consultado.
                    if ($e->kind !== BillingException::VALIDATION) {
                        throw $e;
                    }
                }
            }

            return $data;
        });
    }

    /**
     * La venta se anuló: si su comprobante existe (o puede existir) en SUNAT, falta la nota de crédito.
     */
    public function flagCreditNote(Sale $sale): int
    {
        return SunatDocument::where('sale_id', $sale->id)->live()->update(['credit_note_required' => true]);
    }

    /**
     * @param  callable(FacturacionClient): array<string, mixed>  $call
     */
    protected function send(SunatDocument $document, callable $call): SunatDocument
    {
        try {
            $data = $call($this->client());
        } catch (BillingException $e) {
            if ($e->kind === BillingException::INVALID_TOKEN) {
                $this->settings()->update([
                    'status' => BillingSetting::STATUS_INVALID_TOKEN,
                    'status_message' => $e->getMessage(),
                    'checked_at' => now(),
                ]);
            }

            if ($e->kind !== BillingException::OFFLINE) {
                if ($document->exists) {
                    $document->save(); // guarda el intento
                }

                throw $e;
            }

            // Sin respuesta: puede que el servicio sí lo haya registrado. Se guarda para reintentar
            // con la misma referencia (el servicio devuelve el existente en vez de duplicarlo).
            $document->fill([
                'status' => SunatDocument::STATUS_OFFLINE,
                'sunat_description' => $e->getMessage(),
            ])->save();

            return $document;
        }

        return DB::transaction(function () use ($document, $data) {
            $this->apply($document, $data);
            $this->syncSale($document);

            return $document;
        });
    }

    /**
     * @param  array<string, mixed>  $data  respuesta de la API (ComprobanteResource)
     */
    protected function apply(SunatDocument $document, array $data): void
    {
        $document->fill([
            'remote_id' => $data['id'] ?? $document->remote_id,
            'environment' => $data['entorno'] ?? $document->environment,
            'series' => $data['serie'] ?? $document->series,
            'number' => $data['correlativo'] ?? $document->number,
            'status' => $data['estado'] ?? SunatDocument::STATUS_PENDING,
            'sunat_code' => $data['sunat']['codigo'] ?? null,
            'sunat_description' => $data['sunat']['descripcion'] ?? null,
            'sunat_notes' => ($data['sunat']['notas'] ?? []) ?: null,
            'hash' => $data['hash'] ?? $document->hash,
        ]);

        if (isset($data['fecha_emision'])) {
            $document->issue_date = CarbonImmutable::parse((string) $data['fecha_emision'])->setTimezone('America/Lima')->startOfDay();
        }

        $document->save();
    }

    /**
     * Refleja en la venta el comprobante aceptado. Uno de prueba no la toca: la venta sigue
     * pendiente de facturar y se puede volver a emitir cuantas veces se quiera.
     */
    protected function syncSale(SunatDocument $document): void
    {
        $sale = Sale::withTrashed()->find($document->sale_id);
        if (! $sale || ! $document->isValid() || $document->isTest()) {
            return;
        }

        // Anulada mientras SUNAT respondía: el comprobante ya es válido, así que falta la nota de crédito.
        if ($sale->isCancelled()) {
            $document->update(['credit_note_required' => true]);

            return;
        }

        $sale->update([
            'document_type' => $document->document_type,
            'document_number' => $document->fullNumber(),
            'invoice_date' => $document->issue_date,
            'has_invoice_pending' => false,
        ]);
    }
}
