<?php

namespace App\Livewire\Billing;

use App\Models\BillingSetting;
use App\Services\Billing\BillingException;
use App\Services\Billing\BillingService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Conexión del negocio con Kipusoft Facturación: token de API y series a usar.
 * El token se genera en Kipusoft Facturación → su empresa → Sistemas conectados.
 */
#[Title('Configuración de facturación')]
class BillingSettings extends Component
{
    public string $token = '';

    public ?string $factura_series = null;

    public ?string $boleta_series = null;

    /** @var list<array{tipo_comprobante: string, serie: string, ultimo_correlativo: int}> */
    public array $availableSeries = [];

    public function mount(): void
    {
        $this->authorize('facturacion.configurar');

        $settings = $this->settings;
        $this->factura_series = $settings->factura_series;
        $this->boleta_series = $settings->boleta_series;
    }

    #[Computed]
    public function settings(): BillingSetting
    {
        return BillingSetting::current();
    }

    /**
     * Consulta silenciosa al abrir la página: refleja si el token sigue vigente.
     */
    public function check(BillingService $billing): void
    {
        if (blank($this->settings->api_token)) {
            return;
        }

        $this->runVerify($billing, null, silent: true);
    }

    public function connect(BillingService $billing): void
    {
        $this->authorize('facturacion.configurar');

        $this->validate(['token' => ['required', 'string', 'starts_with:fk_', 'max:200']], [
            'token.required' => 'Pegue el token generado en Kipusoft Facturación.',
            'token.starts_with' => 'El token debe empezar con fk_ (cópielo completo).',
        ]);

        if ($this->runVerify($billing, $this->token)) {
            $this->token = '';
            Flux::toast(variant: 'success', text: 'Conectado con Kipusoft Facturación.');
        }
    }

    public function saveSeries(): void
    {
        $this->authorize('facturacion.configurar');

        $series = fn (string $type) => collect($this->availableSeries)->where('tipo_comprobante', $type)->pluck('serie')->all();

        $this->validate([
            'factura_series' => ['nullable', Rule::in($series('01') ?: [$this->settings->factura_series])],
            'boleta_series' => ['nullable', Rule::in($series('03') ?: [$this->settings->boleta_series])],
        ]);

        $this->settings->update(['factura_series' => $this->factura_series, 'boleta_series' => $this->boleta_series]);
        unset($this->settings);

        Flux::toast(variant: 'success', text: 'Series guardadas.');
    }

    public function disconnect(): void
    {
        $this->authorize('facturacion.configurar');

        $this->settings->update([
            'api_token' => null,
            'status' => BillingSetting::STATUS_NOT_CONFIGURED,
            'status_message' => null,
            'company_ruc' => null,
            'company_name' => null,
            'environment' => null,
        ]);
        unset($this->settings);
        $this->availableSeries = [];

        Flux::toast(text: 'Se quitó el token. Las ventas ya no se podrán facturar hasta configurar uno nuevo.');
    }

    protected function runVerify(BillingService $billing, ?string $token, bool $silent = false): bool
    {
        try {
            $this->availableSeries = $billing->verify($this->settings, $token);

            return true;
        } catch (BillingException $e) {
            if (! $silent) {
                $this->addError('token', $e->getMessage());
            }

            return false;
        } finally {
            unset($this->settings);
            $this->factura_series = $this->settings->factura_series;
            $this->boleta_series = $this->settings->boleta_series;
        }
    }

    public function render(): View
    {
        return view('livewire.billing.billing-settings');
    }
}
