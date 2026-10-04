<?php

namespace App\Services\Shared;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Consulta los datos de un contribuyente en SUNAT a partir de su RUC.
 */
class RucLookup
{
    /**
     * @return array{ruc: string, name: string, trade_name: ?string, address: ?string, phone: ?string, status: ?string, condition: ?string}
     *
     * @throws ValidationException si el RUC no es válido, no existe o el servicio no responde.
     */
    public function find(string $ruc): array
    {
        $ruc = trim($ruc);

        if (! preg_match('/^(10|15|17|20)\d{9}$/', $ruc)) {
            throw $this->failure('El RUC debe tener 11 dígitos y empezar con 10, 15, 17 o 20.');
        }

        try {
            $response = Http::baseUrl(config('services.sunat.url'))
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(10)
                ->get("ruc/{$ruc}", ['token' => config('services.sunat.token')]);
        } catch (ConnectionException) {
            throw $this->failure('No se pudo conectar con el servicio de consulta de RUC. Ingrese los datos manualmente.');
        }

        $name = trim((string) $response->json('razonSocial'));

        if ($response->failed() || $name === '') {
            throw $this->failure('No se encontró el RUC en SUNAT. Verifique el número o ingrese los datos manualmente.');
        }

        $address = collect([
            $response->json('direccion'),
            $response->json('distrito'),
            $response->json('provincia'),
            $response->json('departamento'),
        ])->map(fn ($part) => trim((string) $part))->filter(fn ($part) => $part !== '' && $part !== '-')->implode(', ');

        return [
            'ruc' => $ruc,
            'name' => $name,
            'trade_name' => $this->clean($response->json('nombreComercial')),
            'address' => $address !== '' ? $address : null,
            'phone' => $this->clean(collect((array) $response->json('telefonos'))->first()),
            'status' => $this->clean($response->json('estado')),
            'condition' => $this->clean($response->json('condicion')),
        ];
    }

    protected function clean(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' || $value === '-' ? null : $value;
    }

    protected function failure(string $message): ValidationException
    {
        return ValidationException::withMessages(['document_number' => $message]);
    }
}
