<?php

namespace App\Services\Billing;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Cliente HTTP del servicio central de facturación (Kipusoft Facturación, API v1).
 * Solo transporta: la lógica de negocio vive en BillingService.
 */
class FacturacionClient
{
    public function __construct(protected string $token) {}

    /**
     * Empresa, entorno y series vinculadas al token.
     *
     * @return array<string, mixed>
     */
    public function me(): array
    {
        return $this->send(fn (PendingRequest $http) => $http->timeout(15)->get('yo'));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function emit(array $payload): array
    {
        return $this->send(fn (PendingRequest $http) => $http->post('comprobantes', $payload));
    }

    /**
     * @return array<string, mixed>
     */
    public function show(string $remoteId): array
    {
        return $this->send(fn (PendingRequest $http) => $http->timeout(15)->get("comprobantes/{$remoteId}"));
    }

    /**
     * @return array<string, mixed>
     */
    public function resend(string $remoteId): array
    {
        return $this->send(fn (PendingRequest $http) => $http->post("comprobantes/{$remoteId}/reenviar"));
    }

    /**
     * PDF binario: formato a4 o ticket (80 mm).
     */
    public function pdf(string $remoteId, string $format): string
    {
        try {
            $response = $this->http()->timeout(30)->accept('application/pdf')
                ->get("comprobantes/{$remoteId}/pdf", ['formato' => $format]);
        } catch (ConnectionException) {
            throw $this->offline();
        }

        $this->ensureSuccessful($response);

        return $response->body();
    }

    protected function http(): PendingRequest
    {
        return Http::baseUrl(config('services.facturacion.url'))
            ->withToken($this->token)
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout(config('services.facturacion.timeout'));
    }

    /**
     * @param  callable(PendingRequest): Response  $request
     * @return array<string, mixed>
     */
    protected function send(callable $request): array
    {
        try {
            $response = $request($this->http());
        } catch (ConnectionException) {
            throw $this->offline();
        }

        $this->ensureSuccessful($response);

        return (array) $response->json();
    }

    protected function ensureSuccessful(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        throw match (true) {
            in_array($response->status(), [401, 403], true) => new BillingException(
                BillingException::INVALID_TOKEN,
                'El token de facturación no existe o fue revocado. Genere uno nuevo en Kipusoft Facturación y actualícelo en Facturación → Configuración.',
            ),
            $response->status() === 422 => new BillingException(
                BillingException::VALIDATION,
                (string) ($response->json('message') ?: 'El servicio de facturación rechazó los datos.'),
                (array) $response->json('errors', []),
            ),
            $response->status() === 404 => new BillingException(
                BillingException::SERVER,
                'El servicio de facturación no encontró el recurso solicitado. Revise la URL configurada (FACTURACION_API_URL).',
            ),
            default => new BillingException(
                BillingException::SERVER,
                "El servicio de facturación respondió con un error (HTTP {$response->status()}). Inténtelo más tarde.",
            ),
        };
    }

    protected function offline(): BillingException
    {
        return new BillingException(
            BillingException::OFFLINE,
            'No se pudo conectar con el servicio de facturación ('.config('services.facturacion.url').'). Verifique su conexión e inténtelo más tarde.',
        );
    }
}
