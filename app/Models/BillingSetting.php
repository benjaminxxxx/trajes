<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Conexión del negocio con el servicio central de facturación electrónica.
 * El token lo genera el dueño en Kipusoft Facturación (Sistemas conectados → Alquileres).
 */
class BillingSetting extends Model
{
    use BelongsToAccount;

    public const STATUS_CONNECTED = 'conectado';

    public const STATUS_INVALID_TOKEN = 'token_invalido';

    public const STATUS_OFFLINE = 'sin_conexion';

    public const STATUS_NOT_CONFIGURED = 'sin_configurar';

    protected $fillable = [
        'api_token',
        'factura_series',
        'boleta_series',
        'company_ruc',
        'company_name',
        'environment',
        'status',
        'status_message',
        'checked_at',
    ];

    protected $hidden = ['api_token'];

    protected $casts = [
        'api_token' => 'encrypted',
        'checked_at' => 'datetime',
    ];

    /**
     * Configuración del negocio actual (se crea vacía la primera vez).
     */
    public static function current(): self
    {
        return self::firstOrCreate([]);
    }

    /**
     * Lista para emitir: tiene token y al menos una serie.
     */
    public function isReady(): bool
    {
        return filled($this->api_token) && (filled($this->factura_series) || filled($this->boleta_series));
    }

    public function seriesFor(string $documentType): ?string
    {
        return $documentType === 'factura' ? $this->factura_series : $this->boleta_series;
    }

    public function isProduction(): bool
    {
        return $this->environment === 'produccion';
    }

    /**
     * Últimos 4 caracteres del token para identificarlo sin mostrarlo.
     */
    public function maskedToken(): ?string
    {
        return filled($this->api_token) ? '••••'.substr((string) $this->api_token, -4) : null;
    }
}
