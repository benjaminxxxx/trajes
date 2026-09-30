@php($settings = $this->settings)
<div wire:init="check"
    class="rounded-[2rem] border border-white/80 bg-white/70 p-5 shadow-xl shadow-slate-900/[0.04] backdrop-blur-xl sm:p-8 dark:border-slate-800 dark:bg-slate-900/75">

    <div class="space-y-4">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('dashboard')" icon="home" />
            <flux:breadcrumbs.item>Facturación</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Configuración</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div>
            <flux:heading size="xl">Facturación electrónica</flux:heading>
            <flux:text class="mt-1.5">
                Conecte este negocio con <strong>Kipusoft Facturación</strong> para emitir facturas y boletas de sus ventas.
            </flux:text>
        </div>
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-5">
        <!-- Estado de la conexión -->
        <div class="flex flex-col gap-4 lg:col-span-2">
            @switch($settings->status)
                @case(\App\Models\BillingSetting::STATUS_CONNECTED)
                    <flux:callout variant="success" icon="check-circle" heading="Conectado">
                        <flux:callout.text>
                            {{ $settings->company_name }} · RUC {{ $settings->company_ruc }}<br>
                            Verificado {{ $settings->checked_at?->diffForHumans() }}
                        </flux:callout.text>
                    </flux:callout>
                    @break
                @case(\App\Models\BillingSetting::STATUS_INVALID_TOKEN)
                    <flux:callout variant="danger" icon="no-symbol" heading="El token no existe o fue revocado" data-test="token-revoked">
                        <flux:callout.text>
                            Genere un token nuevo en Kipusoft Facturación (su empresa → Sistemas conectados) y péguelo aquí.
                            Mientras tanto no se pueden facturar ventas.
                        </flux:callout.text>
                    </flux:callout>
                    @break
                @case(\App\Models\BillingSetting::STATUS_OFFLINE)
                    <flux:callout variant="warning" icon="signal-slash" heading="Sin conexión con el servicio de facturación">
                        <flux:callout.text>{{ $settings->status_message }}</flux:callout.text>
                    </flux:callout>
                    @break
                @default
                    <flux:callout icon="information-circle" heading="Aún no está conectado">
                        <flux:callout.text>Pegue el token de su empresa para empezar a facturar.</flux:callout.text>
                    </flux:callout>
            @endswitch

            @if ($settings->environment)
                <div class="rounded-2xl border border-zinc-200 p-4 text-sm dark:border-zinc-700">
                    <p class="text-xs text-zinc-500">Modo de emisión</p>
                    @if ($settings->isProduction())
                        <flux:badge color="emerald" class="mt-1">Producción</flux:badge>
                        <p class="mt-2 text-zinc-600 dark:text-zinc-400">Los comprobantes son reales y tienen valor tributario.</p>
                    @else
                        <flux:badge color="amber" class="mt-1">Modo prueba (beta)</flux:badge>
                        <p class="mt-2 text-zinc-600 dark:text-zinc-400">Los comprobantes van al ambiente de pruebas de SUNAT y no tienen valor tributario.</p>
                    @endif
                </div>
            @endif

            @if ($settings->company_ruc && app(\App\Services\Core\CurrentAccount::class)->get()?->ruc
                && app(\App\Services\Core\CurrentAccount::class)->get()?->ruc !== $settings->company_ruc)
                <flux:callout variant="warning" icon="exclamation-triangle">
                    <flux:callout.text>
                        El RUC de este negocio ({{ app(\App\Services\Core\CurrentAccount::class)->get()?->ruc }})
                        es distinto al de la empresa que factura ({{ $settings->company_ruc }}).
                    </flux:callout.text>
                </flux:callout>
            @endif

            <p class="text-xs text-zinc-500">Servidor: {{ config('services.facturacion.url') }}</p>
        </div>

        <div class="flex flex-col gap-6 lg:col-span-3">
            <!-- Token -->
            <form wire:submit="connect" class="rounded-2xl border border-zinc-200 p-5 dark:border-zinc-700">
                <flux:heading>Token de la empresa</flux:heading>
                <flux:text class="mt-1 text-sm">
                    @if ($settings->maskedToken())
                        Token actual: <code>{{ $settings->maskedToken() }}</code>. Para cambiarlo, pegue uno nuevo.
                    @else
                        En Kipusoft Facturación, abra su empresa → <em>Sistemas conectados</em> → genere un token para <strong>Alquileres</strong>.
                    @endif
                </flux:text>

                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-start">
                    <div class="flex-1">
                        <flux:input wire:model="token" type="password" viewable placeholder="fk_..." autocomplete="off" data-test="billing-token" />
                        <flux:error name="token" />
                    </div>
                    <flux:button type="submit" variant="primary" icon="link">
                        <span wire:loading.remove wire:target="connect">Conectar</span>
                        <span wire:loading wire:target="connect">Verificando...</span>
                    </flux:button>
                </div>

                @if ($settings->maskedToken())
                    <div class="mt-3 flex gap-2">
                        <flux:button size="sm" variant="ghost" icon="arrow-path" wire:click="check">Verificar ahora</flux:button>
                        <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="disconnect"
                            wire:confirm="¿Quitar el token? No se podrán facturar ventas hasta configurar uno nuevo.">Quitar token</flux:button>
                    </div>
                @endif
            </form>

            <!-- Series -->
            @if ($settings->maskedToken())
                <form wire:submit="saveSeries" class="rounded-2xl border border-zinc-200 p-5 dark:border-zinc-700">
                    <flux:heading>Series</flux:heading>
                    <flux:text class="mt-1 text-sm">Las series se crean en Kipusoft Facturación; aquí elige cuál usa este sistema.</flux:text>

                    @php($series = fn ($type) => collect($availableSeries)->where('tipo_comprobante', $type))
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <flux:select wire:model="factura_series" label="Serie de facturas" placeholder="Sin facturas">
                            @foreach ($series('01')->isNotEmpty() ? $series('01') : collect([['serie' => $factura_series]])->filter(fn ($s) => $s['serie']) as $s)
                                <flux:select.option value="{{ $s['serie'] }}">{{ $s['serie'] }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:select wire:model="boleta_series" label="Serie de boletas" placeholder="Sin boletas">
                            @foreach ($series('03')->isNotEmpty() ? $series('03') : collect([['serie' => $boleta_series]])->filter(fn ($s) => $s['serie']) as $s)
                                <flux:select.option value="{{ $s['serie'] }}">{{ $s['serie'] }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    <flux:error name="factura_series" />
                    <flux:error name="boleta_series" />

                    <div class="mt-4 flex justify-end">
                        <flux:button type="submit" variant="primary">Guardar series</flux:button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>
