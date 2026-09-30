<?php

namespace App\Services\Billing;

use RuntimeException;

/**
 * Falla al hablar con el servicio de facturación, clasificada para saber qué hacer.
 */
class BillingException extends RuntimeException
{
    /** Token inexistente o revocado (HTTP 401/403): hay que actualizarlo en la configuración. */
    public const INVALID_TOKEN = 'token_invalido';

    /** El servicio rechazó los datos (HTTP 422): no se creó ningún comprobante. */
    public const VALIDATION = 'validacion';

    /** No hubo respuesta (servicio caído, sin internet, timeout): se puede reintentar. */
    public const OFFLINE = 'sin_conexion';

    /** Respuesta inesperada del servicio (HTTP 5xx u otra). */
    public const SERVER = 'servidor';

    /**
     * @param  array<string, list<string>>  $errors
     */
    public function __construct(
        public readonly string $kind,
        string $message,
        public readonly array $errors = [],
    ) {
        parent::__construct($message);
    }

    /**
     * Todos los mensajes en una lista legible (para mostrar al usuario).
     *
     * @return list<string>
     */
    public function messages(): array
    {
        $messages = array_values(array_filter(array_map('strval', array_merge(...array_values($this->errors)))));

        return $messages ?: [$this->getMessage()];
    }
}
