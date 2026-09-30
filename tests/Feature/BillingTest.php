<?php

use App\Livewire\Billing\BillingSettings;
use App\Livewire\Billing\InvoiceSaleModal;
use App\Livewire\Billing\SunatDocumentIndex;
use App\Livewire\Sale\SaleDetailModal;
use App\Livewire\Sale\SaleForm;
use App\Models\BillingSetting;
use App\Models\Client;
use App\Models\Sale;
use App\Models\SunatDocument;
use App\Services\Billing\BillingException;
use App\Services\Billing\BillingService;
use App\Services\Sale\SaleService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

const API = 'http://facturacion.test/api/v1';

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    config(['services.facturacion.url' => API]);

    $this->admin = createAdmin();
    $this->actingAs($this->admin);
});

/**
 * Venta completada con precios que no dividen exacto entre 1.18 (prueba el cuadre al céntimo).
 */
function billingSale(?Client $client = null, array $prices = [149.90, 35.50]): Sale
{
    $lines = [];
    foreach ($prices as $price) {
        $variant = createProductIn(null, stock: 5);
        $lines[] = ['product_id' => $variant->product_id, 'size_id' => null, 'color_id' => null, 'quantity' => 1, 'unit_price' => $price, 'discount_percent' => 0];
    }

    return app(SaleService::class)->register(
        ['client_id' => $client?->id, 'document_type' => 'boleta', 'has_invoice_pending' => true, 'sale_date' => now()->subWeek()->toDateString()],
        $lines,
    );
}

function rucClient(): Client
{
    return Client::create([
        'uuid' => (string) Str::uuid(),
        'type' => 'company',
        'document_type' => 'RUC',
        'document_number' => '20100070970',
        'company_name' => 'SUPERMERCADOS PERUANOS S.A.',
        'display_name' => 'SUPERMERCADOS PERUANOS S.A.',
    ]);
}

function connectBilling(string $environment = 'produccion'): BillingSetting
{
    $settings = BillingSetting::current();
    $settings->update([
        'api_token' => 'fk_token_de_prueba',
        'factura_series' => 'F001',
        'boleta_series' => 'B001',
        'status' => BillingSetting::STATUS_CONNECTED,
        'environment' => $environment,
    ]);

    return $settings;
}

/**
 * Respuesta de la API (ComprobanteResource) para un POST/GET de comprobante.
 */
function apiDocument(array $overrides = []): array
{
    return array_replace_recursive([
        'id' => (string) Str::uuid(),
        'entorno' => 'produccion',
        'tipo_comprobante' => '03',
        'serie' => 'B001',
        'correlativo' => 1,
        'numero' => 'B001-1',
        'fecha_emision' => now('America/Lima')->toIso8601String(),
        'estado' => 'aceptado',
        'valido' => true,
        'sunat' => ['codigo' => '0', 'descripcion' => 'La Boleta numero B001-1, ha sido aceptada', 'notas' => []],
        'hash' => 'HASH123',
    ], $overrides);
}

function meResponse(): array
{
    return [
        'sistema' => 'alquiler',
        'empresa' => ['ruc' => '20000000001', 'razon_social' => 'EMPRESA DEMO SAC', 'entorno' => 'beta', 'certificado_vence_en' => '2027-09-30'],
        'series' => [
            ['tipo_comprobante' => '01', 'serie' => 'F001', 'ultimo_correlativo' => 0],
            ['tipo_comprobante' => '03', 'serie' => 'B001', 'ultimo_correlativo' => 3],
        ],
    ];
}

// ---------------- Configuración ----------------

test('connecting stores the encrypted token, company data and series', function () {
    Http::fake([API.'/yo' => Http::response(meResponse())]);

    Livewire::test(BillingSettings::class)
        ->set('token', 'fk_nuevo_token')
        ->call('connect')
        ->assertHasNoErrors();

    $settings = BillingSetting::current();
    expect($settings->status)->toBe(BillingSetting::STATUS_CONNECTED)
        ->and($settings->company_ruc)->toBe('20000000001')
        ->and($settings->factura_series)->toBe('F001')
        ->and($settings->boleta_series)->toBe('B001')
        ->and($settings->api_token)->toBe('fk_nuevo_token')
        ->and(DB::table('billing_settings')->value('api_token'))->not->toContain('fk_nuevo_token');

    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer fk_nuevo_token'));
});

test('a revoked token is shown as not existing or revoked', function () {
    connectBilling();
    Http::fake([API.'/yo' => Http::response(['message' => 'Token de API inválido o revocado.'], 401)]);

    Livewire::test(BillingSettings::class)
        ->call('check')
        ->assertSee('El token no existe o fue revocado');

    expect(BillingSetting::current()->status)->toBe(BillingSetting::STATUS_INVALID_TOKEN);

    $this->get(route('billing.documents.index'))->assertOk()->assertSee('no existe o fue revocado');
});

test('an unreachable service is reported without breaking the page', function () {
    connectBilling();
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    Livewire::test(BillingSettings::class)->call('check')->assertSee('Sin conexión con el servicio de facturación');
    expect(BillingSetting::current()->status)->toBe(BillingSetting::STATUS_OFFLINE);
});

test('billing pages require their permissions', function () {
    $this->actingAs(createSeller());

    $this->get(route('billing.settings'))->assertForbidden();
    $this->get(route('billing.documents.index'))->assertForbidden();
});

// ---------------- Armado del comprobante ----------------

test('invoice lines add up exactly to the sale total', function () {
    connectBilling();
    $sale = billingSale(prices: [149.90, 35.50, 0.10, 19.99]);

    $lines = app(BillingService::class)->lines($sale);
    $sum = array_sum(array_map(fn ($l) => (int) round((float) $l['importe'] * 100), $lines));

    expect($sum)->toBe((int) round((float) $sale->total * 100))
        ->and($lines)->toHaveCount(4)
        ->and($lines[0]['unidad'])->toBe('NIU');

    // cantidad × precio (10 decimales) vuelve al importe de la línea.
    foreach ($lines as $line) {
        expect(round($line['cantidad'] * (float) $line['precio_unitario'], 2))->toBe(round((float) $line['importe'], 2));
    }
});

/**
 * Registra una venta con líneas [precio con IGV, cantidad, descuento %, monto fijado de la línea].
 */
function saleWithLines(array $lines): Sale
{
    return app(SaleService::class)->register(
        ['document_type' => 'boleta', 'has_invoice_pending' => true, 'sale_date' => now()->toDateString()],
        array_map(function (array $l) {
            $variant = createProductIn(null, stock: 100);

            return ['product_id' => $variant->product_id, 'size_id' => null, 'color_id' => null,
                'unit_price' => $l[0], 'quantity' => $l[1] ?? 1, 'discount_percent' => $l[2] ?? 0, 'line_total' => $l[3] ?? null];
        }, $lines),
    );
}

/**
 * @return list<string>
 */
function invoiceAmounts(Sale $sale): array
{
    return array_column(app(BillingService::class)->lines($sale), 'importe');
}

test('each line is sent with exactly the amount charged', function (array $lines, array $expected, string $total) {
    $sale = saleWithLines($lines);

    expect(invoiceAmounts($sale))->toBe($expected)
        ->and(number_format((float) $sale->total, 2, '.', ''))->toBe($total)
        ->and($sale->details()->orderBy('id')->pluck('line_total')->map(fn ($v) => (string) $v)->all())->toBe($expected);
})->with([
    'precio "no real" 23.39' => [[[23.39]], ['23.39'], '23.39'],
    'montos que no se recuperan desde el neto (0.10 y 0.03)' => [[[149.90], [0.10], [0.03]], ['149.90', '0.10', '0.03'], '150.03'],
    'descuento 10 %: 2 × 35.50' => [[[35.50, 2, 10]], ['63.90'], '63.90'],
    'monto fijado a mano: 3 unidades por 100.00' => [[[33.33, 3, 0, 100.00]], ['100.00'], '100.00'],
    'terno + corbatas con descuento' => [[[349.90], [35.50, 2, 10]], ['349.90', '63.90'], '413.80'],
]);

test('old sales without line_total are rebuilt and still add up to the sale total', function () {
    $sale = saleWithLines([[149.90], [0.10], [0.03], [0.29]]);
    // Venta registrada antes de existir line_total.
    $sale->details()->update(['line_total' => null]);

    $amounts = invoiceAmounts($sale->fresh());
    $sum = array_sum(array_map(fn ($a) => (int) round((float) $a * 100), $amounts));

    // El total siempre cuadra. Pero sin line_total las líneas son aproximadas: 0.10 → 0.09, 0.03 → 0.04 y
    // 0.29 → 0.30 suman +0.01, que se descuenta de la mayor (149.90 → 149.89). Por eso las ventas nuevas
    // guardan el monto cobrado.
    expect($sum)->toBe((int) round((float) $sale->total * 100))
        ->and($amounts)->toBe(['149.89', '0.09', '0.04', '0.30']);
});

test('the service receives the fixed amounts and the invoice total equals the sale total', function () {
    connectBilling();
    $sale = saleWithLines([[33.33, 3, 0, 100.00], [0.10], [23.39]]);
    Http::fake([API.'/comprobantes' => Http::response(apiDocument(), 201)]);

    app(BillingService::class)->invoice($sale, 'boleta', now()->toDateString());

    Http::assertSent(fn (Request $request) => array_column($request->data()['items'], 'importe') === ['100.00', '0.10', '23.39']
        && array_column($request->data()['items'], 'cantidad') === [3, 1, 1]);
    expect(number_format((float) $sale->total, 2, '.', ''))->toBe('123.49');
});

test('factura requires a RUC client, dates are limited and cancelled sales are not invoiced', function () {
    connectBilling();
    $billing = app(BillingService::class);
    $sale = billingSale(createClient());

    expect($billing->problems($sale, 'factura', now()->toDateString()))->toContain('La factura requiere un cliente con RUC. Asigne el cliente a la venta o emita una boleta.')
        ->and($billing->problems($sale, 'boleta', now()->toDateString()))->toBe([])
        ->and(implode(' ', $billing->problems($sale, 'boleta', now()->subDays(4)->toDateString())))->toContain('3 días')
        ->and(implode(' ', $billing->problems($sale, 'boleta', now()->addDay()->toDateString())))->toContain('futura');

    app(SaleService::class)->cancel($sale, 'Prueba de anulación');
    expect($billing->problems($sale->fresh(), 'boleta', now()->toDateString()))->toContain('La venta está anulada.');
});

test('boletas over 700 need an identified client', function () {
    connectBilling();
    $sale = billingSale(prices: [750]);

    expect(implode(' ', app(BillingService::class)->problems($sale, 'boleta', now()->toDateString())))->toContain('700');
});

// ---------------- Emisión ----------------

test('invoicing a week-old sale with today as issue date stores the accepted boleta', function () {
    connectBilling();
    $sale = billingSale(createClient());
    Http::fake([API.'/comprobantes' => Http::response(apiDocument(), 201)]);

    $document = app(BillingService::class)->invoice($sale, 'boleta', now()->toDateString());

    expect($document->status)->toBe(SunatDocument::STATUS_ACCEPTED)
        ->and($document->fullNumber())->toBe('B001-1')
        ->and($document->reference)->toBe("venta-{$sale->id}-1")
        ->and($document->issue_date->toDateString())->toBe(now('America/Lima')->toDateString());

    $sale->refresh();
    expect($sale->has_invoice_pending)->toBeFalse()
        ->and($sale->document_type)->toBe('boleta')
        ->and($sale->document_number)->toBe('B001-1');

    Http::assertSent(function (Request $request) use ($sale) {
        $data = $request->data();
        $total = array_sum(array_map(fn ($i) => (float) $i['importe'], $data['items']));

        return $request->url() === API.'/comprobantes'
            && ! isset($data['items'][0]['precio_unitario']) // se envía el monto fijado, no un precio derivado
            && $data['tipo_comprobante'] === '03'
            && $data['serie'] === 'B001'
            && $data['cliente']['tipo_documento'] === '1'
            && abs($total - (float) $sale->total) < 0.005
            && str_starts_with($data['fecha_emision'], now('America/Lima')->toDateString());
    });
});

test('a sunat rejection is saved and the sale can be invoiced again with a new reference', function () {
    connectBilling();
    $sale = billingSale(rucClient());
    Http::fakeSequence(API.'/comprobantes')
        ->push(apiDocument(['tipo_comprobante' => '01', 'serie' => 'F001', 'estado' => 'rechazado', 'valido' => false,
            'sunat' => ['codigo' => '2800', 'descripcion' => 'Tipo de documento no permitido']]), 201)
        ->push(apiDocument(['tipo_comprobante' => '01', 'serie' => 'F001', 'correlativo' => 2]), 201);

    $rejected = app(BillingService::class)->invoice($sale, 'factura', now()->toDateString());
    expect($rejected->status)->toBe(SunatDocument::STATUS_REJECTED)
        ->and($sale->fresh()->has_invoice_pending)->toBeTrue();

    $accepted = app(BillingService::class)->invoice($sale->fresh(), 'factura', now()->toDateString());
    expect($accepted->reference)->toBe("venta-{$sale->id}-2")
        ->and($accepted->fullNumber())->toBe('F001-2')
        ->and($sale->fresh()->document_number)->toBe('F001-2')
        ->and(SunatDocument::count())->toBe(2);
});

test('when the service is unreachable the attempt is saved and retried with the same reference', function () {
    connectBilling();
    $sale = billingSale();
    $calls = 0;
    $references = [];
    Http::fake(function (Request $request) use (&$calls, &$references) {
        $calls++;
        $references[] = $request->data()['referencia_externa'] ?? null;
        if ($calls === 1) {
            throw new ConnectionException('timeout');
        }

        return Http::response(apiDocument(), 200); // 200 = ya existía esa referencia
    });

    $document = app(BillingService::class)->invoice($sale, 'boleta', now()->toDateString());
    expect($document->status)->toBe(SunatDocument::STATUS_OFFLINE)
        ->and($sale->fresh()->has_invoice_pending)->toBeTrue();

    // Mientras no haya respuesta, no se puede emitir otro comprobante para la venta.
    expect(app(BillingService::class)->problems($sale->fresh(), 'boleta', now()->toDateString()))
        ->toContain('La venta ya tiene un comprobante electrónico vigente o pendiente de respuesta.');

    $document = app(BillingService::class)->refresh($document);

    expect($document->status)->toBe(SunatDocument::STATUS_ACCEPTED)
        ->and($document->attempts)->toBe(2)
        ->and($sale->fresh()->has_invoice_pending)->toBeFalse();

    expect($references)->toBe(["venta-{$sale->id}-1", "venta-{$sale->id}-1"]);
});

test('a document pending in sunat is queried and resent through the service', function () {
    connectBilling();
    $sale = billingSale();
    $remote = (string) Str::uuid();
    Http::fake([
        API.'/comprobantes' => Http::response(apiDocument(['id' => $remote, 'estado' => 'error_envio', 'valido' => false, 'sunat' => ['codigo' => 'HTTP', 'descripcion' => 'Unauthorized']]), 201),
        API."/comprobantes/{$remote}" => Http::response(apiDocument(['id' => $remote, 'estado' => 'error_envio', 'valido' => false])),
        API."/comprobantes/{$remote}/reenviar" => Http::response(apiDocument(['id' => $remote])),
    ]);

    $document = app(BillingService::class)->invoice($sale, 'boleta', now()->toDateString());
    expect($document->status)->toBe(SunatDocument::STATUS_SEND_ERROR)
        ->and($document->statusLabel())->toBe('Pendiente en SUNAT');

    $document = app(BillingService::class)->refresh($document);

    expect($document->status)->toBe(SunatDocument::STATUS_ACCEPTED);
    Http::assertSent(fn (Request $r) => $r->url() === API."/comprobantes/{$remote}/reenviar");
});

test('a revoked token while invoicing saves nothing and flags the configuration', function () {
    connectBilling();
    $sale = billingSale();
    Http::fake([API.'/comprobantes' => Http::response(['message' => 'Token de API inválido o revocado.'], 401)]);

    expect(fn () => app(BillingService::class)->invoice($sale, 'boleta', now()->toDateString()))
        ->toThrow(BillingException::class, 'no existe o fue revocado');

    expect(SunatDocument::count())->toBe(0)
        ->and(BillingSetting::current()->status)->toBe(BillingSetting::STATUS_INVALID_TOKEN);
});

test('api validation errors are shown in the invoice modal', function () {
    connectBilling();
    $sale = billingSale();
    Http::fake([API.'/comprobantes' => Http::response([
        'message' => 'La serie B001 no está registrada.',
        'errors' => ['serie' => ['La serie B001 no está registrada para BOLETA DE VENTA ELECTRÓNICA en esta empresa.']],
    ], 422)]);

    Livewire::test(InvoiceSaleModal::class)
        ->call('open', $sale->id)
        ->call('submit')
        ->assertSet('apiErrors', ['La serie B001 no está registrada para BOLETA DE VENTA ELECTRÓNICA en esta empresa.'])
        ->assertSee('no está registrada');

    expect(SunatDocument::count())->toBe(0);
});

// ---------------- Pantallas de ventas ----------------

test('the invoice modal previews the sale and sends it', function () {
    connectBilling();
    $sale = billingSale(rucClient());
    Http::fake([API.'/comprobantes' => Http::response(apiDocument(['tipo_comprobante' => '01', 'serie' => 'F001']), 201)]);

    Livewire::test(InvoiceSaleModal::class)
        ->call('open', $sale->id)
        ->assertSet('documentType', 'factura')
        ->assertSet('issueDate', now('America/Lima')->toDateString())
        ->assertSee('SUPERMERCADOS PERUANOS S.A.')
        ->assertSee('S/ '.number_format($sale->total, 2))
        ->call('submit')
        ->assertDispatched('saleUpdated')
        ->assertSee('F001-1')
        ->assertSee('Aceptado');
});

test('sales list shows the invoice button and the electronic status', function () {
    connectBilling();
    $pending = billingSale();
    $invoiced = billingSale();
    SunatDocument::create([
        'sale_id' => $invoiced->id, 'reference' => 'venta-x-1', 'remote_id' => (string) Str::uuid(),
        'document_type' => 'boleta', 'series' => 'B001', 'number' => 7, 'issue_date' => now(), 'total' => $invoiced->total,
        'status' => SunatDocument::STATUS_ACCEPTED, 'request_payload' => [],
    ]);

    $this->get(route('sale.sales.index'))
        ->assertOk()
        ->assertSee("facturar-{$pending->id}", false)
        ->assertDontSee("facturar-{$invoiced->id}", false)
        ->assertSee('B001-7');
});

test('without billing configured the manual invoicing keeps working', function () {
    $sale = billingSale();

    $this->get(route('sale.sales.index'))->assertOk()->assertDontSee("facturar-{$sale->id}", false);

    Livewire::test(SaleDetailModal::class)
        ->call('loadSale', $sale->id)
        ->assertSee('Marcar facturada')
        ->set('document_type', 'boleta')
        ->set('document_number', 'B001-99')
        ->call('invoice')
        ->assertHasNoErrors();

    expect($sale->fresh()->has_invoice_pending)->toBeFalse();
});

test('cancelling an invoiced sale restores stock and asks for a credit note', function () {
    connectBilling();
    $sale = billingSale();
    Http::fake([API.'/comprobantes' => Http::response(apiDocument(), 201)]);
    app(BillingService::class)->invoice($sale, 'boleta', now()->toDateString());
    $variant = $sale->details()->first()->variant;
    $stockBefore = $variant->fresh()->stock;

    Livewire::test(SaleDetailModal::class)
        ->call('loadSale', $sale->id)
        ->assertSee('nota de crédito') // aviso en la confirmación
        ->set('cancellationReason', 'El cliente devolvió la prenda')
        ->call('cancel');

    expect($sale->fresh()->isCancelled())->toBeTrue()
        ->and($variant->fresh()->stock)->toBe($stockBefore + 1)
        ->and(SunatDocument::first()->credit_note_required)->toBeTrue();

    Livewire::test(SaleDetailModal::class)
        ->call('loadSale', $sale->id)
        ->assertSee('Emita la nota de crédito por la Boleta B001-1');

    Livewire::test(SunatDocumentIndex::class)
        ->assertSee('requiere nota de crédito')
        ->set('filter', 'nota_credito')
        ->assertSee('B001-1');
});

test('with billing enabled the sale form leaves boletas pending instead of asking for a number', function () {
    connectBilling();
    $variant = createProductIn(null, stock: 3);

    $component = Livewire::test(SaleForm::class)->assertDontSee('N.º de comprobante');

    $component->set('document_type', 'boleta')
        ->set('document_number', 'B001-123')
        ->call('handlePickedItems', 'sale-gallery', [[
            'product_id' => $variant->product_id, 'product_name' => 'Terno', 'product_code' => 'T1', 'variant_label' => '',
            'size_id' => null, 'color_id' => null, 'quantity' => 1, 'unit_price' => 50,
            'physical_product_id' => null, 'physical_code' => null,
        ]])
        ->call('save');

    $sale = Sale::latest('id')->first();
    expect($sale)->not->toBeNull()
        ->and($sale->has_invoice_pending)->toBeTrue()
        ->and($sale->document_number)->toBeNull();
});

test('the pdf is fetched from the service with the account token', function () {
    connectBilling();
    $document = SunatDocument::create([
        'sale_id' => billingSale()->id, 'reference' => 'venta-pdf-1', 'remote_id' => $remote = (string) Str::uuid(),
        'document_type' => 'boleta', 'series' => 'B001', 'number' => 3, 'issue_date' => now(), 'total' => 10,
        'status' => SunatDocument::STATUS_ACCEPTED, 'request_payload' => [],
    ]);
    Http::fake([API."/comprobantes/{$remote}/pdf*" => Http::response('%PDF-1.7 prueba', 200, ['Content-Type' => 'application/pdf'])]);

    $this->get(route('billing.documents.pdf', [$document, 'ticket']))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'formato=ticket') && $r->hasHeader('Authorization', 'Bearer fk_token_de_prueba'));
});

test('documents from another business are not visible', function () {
    connectBilling();
    $document = SunatDocument::create([
        'sale_id' => billingSale()->id, 'reference' => 'venta-otra-1', 'remote_id' => (string) Str::uuid(),
        'document_type' => 'boleta', 'series' => 'B001', 'number' => 1, 'issue_date' => now(), 'total' => 10,
        'status' => SunatDocument::STATUS_ACCEPTED, 'request_payload' => [],
    ]);

    $other = createTestAccount();
    $intruder = createAdmin($other);
    actingAsIn($intruder, $other);

    $this->get(route('billing.documents.pdf', [$document->id, 'a4']))->assertNotFound();
});

// ---------------- Modo prueba (beta): no cuenta como facturado ----------------

test('in test mode a sale can be invoiced again as many times as wanted', function () {
    connectBilling('beta');
    $sale = billingSale();
    Http::fakeSequence(API.'/comprobantes')
        ->push(apiDocument(['entorno' => 'beta', 'correlativo' => 1]), 201)
        ->push(apiDocument(['entorno' => 'beta', 'correlativo' => 2]), 201)
        ->push(apiDocument(['entorno' => 'beta', 'correlativo' => 3]), 201);

    $billing = app(BillingService::class);
    foreach ([1, 2, 3] as $attempt) {
        $document = $billing->invoice($sale->fresh(), 'boleta', now()->toDateString());

        expect($document->isTest())->toBeTrue()
            ->and($document->isValid())->toBeTrue()
            ->and($document->reference)->toBe("venta-{$sale->id}-{$attempt}");

        // La venta sigue pendiente, sin número, y se puede volver a facturar.
        $fresh = $sale->fresh();
        expect($fresh->has_invoice_pending)->toBeTrue()
            ->and($fresh->document_number)->toBeNull()
            ->and($fresh->liveSunatDocument)->toBeNull()
            ->and($billing->problems($fresh, 'boleta', now()->toDateString()))->toBe([]);
    }

    expect(SunatDocument::where('sale_id', $sale->id)->count())->toBe(3);
});

test('a production document still blocks invoicing again', function () {
    connectBilling('produccion');
    $sale = billingSale();
    Http::fake([API.'/comprobantes' => Http::response(apiDocument(['entorno' => 'produccion']), 201)]);

    app(BillingService::class)->invoice($sale, 'boleta', now()->toDateString());

    expect($sale->fresh()->has_invoice_pending)->toBeFalse()
        ->and(app(BillingService::class)->problems($sale->fresh(), 'boleta', now()->toDateString()))
        ->toContain('La venta ya tiene un comprobante electrónico vigente o pendiente de respuesta.');
});

test('the environment confirmed by the service wins over the local setting', function () {
    // Configurado como beta aquí, pero la empresa ya pasó a producción en el servicio.
    connectBilling('beta');
    $sale = billingSale();
    Http::fake([API.'/comprobantes' => Http::response(apiDocument(['entorno' => 'produccion']), 201)]);

    $document = app(BillingService::class)->invoice($sale, 'boleta', now()->toDateString());

    expect($document->isTest())->toBeFalse()
        ->and($sale->fresh()->document_number)->toBe('B001-1');
});

test('a test attempt without response does not block the sale', function () {
    connectBilling('beta');
    $sale = billingSale();
    Http::fake(fn () => throw new ConnectionException('timeout'));

    $document = app(BillingService::class)->invoice($sale, 'boleta', now()->toDateString());

    expect($document->status)->toBe(SunatDocument::STATUS_OFFLINE)
        ->and($document->environment)->toBe('beta')
        ->and(app(BillingService::class)->problems($sale->fresh(), 'boleta', now()->toDateString()))->toBe([]);
});

test('cancelling a sale invoiced only in test mode does not ask for a credit note', function () {
    connectBilling('beta');
    $sale = billingSale();
    Http::fake([API.'/comprobantes' => Http::response(apiDocument(['entorno' => 'beta']), 201)]);
    app(BillingService::class)->invoice($sale, 'boleta', now()->toDateString());

    app(SaleService::class)->cancel($sale->fresh(), 'Venta de prueba');

    expect(SunatDocument::first()->credit_note_required)->toBeFalse();
});

test('the modal and the sales list treat test documents as test', function () {
    connectBilling('beta');
    $sale = billingSale();
    Http::fake([API.'/comprobantes' => Http::response(apiDocument(['entorno' => 'beta']), 201)]);

    Livewire::test(InvoiceSaleModal::class)
        ->call('open', $sale->id)
        ->call('submit')
        ->assertSee('modo prueba')
        ->assertSee('Volver a facturar')
        ->call('startOver')
        ->assertSet('documentId', null)
        ->assertSee('Enviar a SUNAT');

    $this->get(route('sale.sales.index'))
        ->assertOk()
        ->assertSee("facturar-{$sale->id}", false)   // sigue facturable
        ->assertSee('Prueba B001-1');
});

test('sales marked as invoiced by a test document are released by the data fix', function () {
    $sale = billingSale();
    $sale->update(['document_number' => 'B001-1', 'has_invoice_pending' => false]);
    SunatDocument::create([
        'sale_id' => $sale->id, 'reference' => 'venta-legacy-1', 'environment' => 'beta',
        'document_type' => 'boleta', 'series' => 'B001', 'number' => 1, 'issue_date' => now(), 'total' => $sale->total,
        'status' => SunatDocument::STATUS_OBSERVED, 'request_payload' => [],
    ]);

    (require database_path('migrations/2026_10_01_120000_release_sales_invoiced_in_test_mode.php'))->up();

    expect($sale->fresh()->has_invoice_pending)->toBeTrue()
        ->and($sale->fresh()->document_number)->toBeNull();
});

test('the client of a sale with an issued electronic document cannot be changed', function () {
    connectBilling();
    $sale = billingSale();
    Http::fake([API.'/comprobantes' => Http::response(apiDocument(), 201)]);
    app(BillingService::class)->invoice($sale, 'boleta', now()->toDateString());
    $originalClientId = $sale->fresh()->client_id;
    $otherClient = createClient();

    Livewire::test(SaleDetailModal::class)
        ->call('loadSale', $sale->id)
        ->assertDontSee('Nuevo cliente')
        ->dispatch('client-created', clientId: $otherClient->id)
        ->assertSet('client_id', $originalClientId);

    expect($sale->fresh()->client_id)->toBe($originalClientId);
});
