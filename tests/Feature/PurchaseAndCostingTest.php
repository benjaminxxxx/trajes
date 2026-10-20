<?php

use App\Livewire\Purchasing\PurchaseDetailModal;
use App\Livewire\Purchasing\PurchaseForm;
use App\Livewire\Rental\RentalForm;
use App\Livewire\Sale\SaleForm;
use App\Livewire\Shared\ProductGallery;
use App\Livewire\Shared\ProductPicker;
use App\Models\CashMovement;
use App\Models\PhysicalProduct;
use App\Models\Product;
use App\Models\Purchase;
use App\Services\Purchasing\PurchaseService;
use App\Services\Rental\RentalService;
use App\Services\Sale\SaleService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = createAdmin();
    $this->actingAs($this->admin);
});

function makeProduct(bool $rentable): Product
{
    $code = Str::random(6);

    return Product::create([
        'code' => $code,
        'name' => "Producto {$code}",
        'slug' => Str::slug($code),
        'allow_sale' => true,
        'allow_rental' => $rentable,
    ]);
}

function buy(Product $product, int $quantity, float $unitPrice, float $discount = 0): Purchase
{
    return app(PurchaseService::class)->register(
        ['document_type' => 'factura', 'issue_date' => now()->toDateString()],
        [['product_id' => $product->id, 'size_id' => null, 'color_id' => null, 'quantity' => $quantity, 'unit_price' => $unitPrice, 'discount_percent' => $discount]],
    );
}

function sell(Product $product, int $quantity, float $grossUnitPrice, ?int $physicalProductId = null, ?float $lineTotal = null)
{
    return app(SaleService::class)->register(
        ['document_type' => 'boleta', 'sale_date' => now()->toDateString()],
        [[
            'product_id' => $product->id, 'size_id' => null, 'color_id' => null,
            'quantity' => $quantity, 'unit_price' => $grossUnitPrice,
            'line_total' => $lineTotal, 'physical_product_id' => $physicalProductId,
        ]],
    );
}

test('purchases page renders', function () {
    buy(makeProduct(false), 2, 10);

    $this->get(route('purchasing.purchases.index'))->assertOk()->assertSee('Factura');
});

test('physical units copy the net unit cost of the purchase', function () {
    $product = makeProduct(true);
    buy($product, 2, 100, discount: 10);

    expect(PhysicalProduct::pluck('purchase_cost')->map(fn ($cost) => (float) $cost)->all())->toBe([90.0, 90.0]);
});

test('a factura entered with IGV included splits the tax and costs units without it', function () {
    $product = makeProduct(true);

    $purchase = app(PurchaseService::class)->register(
        ['document_type' => 'factura', 'prices_include_tax' => true, 'issue_date' => now()->toDateString()],
        [['product_id' => $product->id, 'size_id' => null, 'color_id' => null, 'quantity' => 6, 'unit_price' => 70, 'discount_percent' => 0]],
    );

    expect((float) $purchase->total)->toEqual(420.0)
        ->and((float) $purchase->subtotal)->toEqual(355.93)
        ->and((float) $purchase->tax)->toEqual(64.07)
        ->and((float) CashMovement::where('origin', CashMovement::ORIGIN_PURCHASE_EXPENSE)->sole()->amount)->toEqual(420.0)
        ->and(PhysicalProduct::pluck('purchase_cost')->unique()->map(fn ($cost) => (float) $cost)->all())->toBe([59.32]);
});

/**
 * Facturas reales de proveedores: [importes por línea con IGV, op. gravada, IGV, total].
 * Todas sacan la base línea por línea; sobre el total, dos saldrían 1 céntimo distintas.
 */
dataset('supplier invoices', [
    'chalecos y sesgo (F. electrónica)' => [[135, 120, 450, 300], 851.70, 153.30, 1005.00],
    'Mia Khalessi F001-00000541' => [[420, 680, 425, 840, 550, 960, 660, 960], 4656.77, 838.23, 5495.00],
    'bisutería F003-00000047' => [[60, 45, 30, 24, 40, 144, 144], 412.71, 74.29, 487.00],
    'Gilda F101-00000136' => [[150, 150, 20, 160, 15], 419.49, 75.51, 495.00],
    'Inversiones Sukey F005-00000068' => [[255, 90, 80, 190], 521.19, 93.81, 615.00],
]);

test('totals match real supplier invoices to the cent', function (array $lines, float $taxable, float $tax, float $total) {
    expect(app(PurchaseService::class)->totals($lines, pricesIncludeTax: true))
        ->toBe(['subtotal' => $taxable, 'tax' => $tax, 'total' => $total]);
})->with('supplier invoices');

test('a purchase registered with the invoice lines books its exact IGV and pays its total', function (array $lines, float $taxable, float $tax, float $total) {
    $product = makeProduct(false);

    $purchase = app(PurchaseService::class)->register(
        ['document_type' => 'factura', 'prices_include_tax' => true, 'issue_date' => now()->toDateString()],
        array_map(fn (float $amount) => ['product_id' => $product->id, 'size_id' => null, 'color_id' => null, 'quantity' => 1, 'unit_price' => $amount, 'discount_percent' => 0], $lines),
    );

    expect((float) $purchase->subtotal)->toEqual($taxable)
        ->and((float) $purchase->tax)->toEqual($tax)
        ->and((float) CashMovement::sole()->amount)->toEqual($total);
})->with('supplier invoices');

test('lines grouped differently from the invoice use the printed IGV when it is only rounding', function () {
    $product = makeProduct(true);
    // Mismo comprobante de S/ 1,005 registrado como Terno Sesgo M (1×195), S (2×195) y 16 (3×140).
    $lines = [[1, 195], [2, 195], [3, 140]];
    $header = ['document_type' => 'factura', 'prices_include_tax' => true, 'issue_date' => now()->toDateString(), 'document_total' => 1005];
    $rows = array_map(fn (array $line) => ['product_id' => $product->id, 'size_id' => null, 'color_id' => null, 'quantity' => $line[0], 'unit_price' => $line[1], 'discount_percent' => 0], $lines);

    expect(app(PurchaseService::class)->totals([195, 390, 420], true))->toBe(['subtotal' => 851.69, 'tax' => 153.31, 'total' => 1005.0]);

    $purchase = app(PurchaseService::class)->register($header + ['document_tax' => 153.30], $rows);

    expect((float) $purchase->subtotal)->toEqual(851.70)
        ->and((float) $purchase->tax)->toEqual(153.30)
        ->and((float) $purchase->total)->toEqual(1005.0)
        ->and((float) CashMovement::sole()->amount)->toEqual(1005.0);

    // Una diferencia que no es redondeo no se confirma: las líneas están mal.
    app(PurchaseService::class)->register($header + ['document_tax' => 150.00], $rows);
})->throws(RuntimeException::class, 'no cuadra');

test('the purchase form shows when the printed IGV corrects a rounding difference', function () {
    $product = makeProduct(false);

    $form = Livewire::test(PurchaseForm::class)
        ->call('addCreatedProduct', $product->id, 'purchase')
        ->call('addCreatedProduct', $product->id, 'purchase')
        ->set('document_type', 'factura');

    [$first, $second] = array_keys($form->get('items'));

    $form->set("items.{$first}.unit_price", 195)
        ->set("items.{$second}.quantity", 2)
        ->set("items.{$second}.unit_price", 405)
        ->set('document_tax', 153.30)
        ->assertSet('taxDifference', 0.01)
        ->assertSet('totals', ['subtotal' => 851.70, 'tax' => 153.30, 'total' => 1005.0])
        ->assertSee('Por redondeo');
});

test('a boleta entered with IGV included costs exactly what was paid', function () {
    $product = makeProduct(false);

    $purchase = app(PurchaseService::class)->register(
        ['document_type' => 'boleta', 'prices_include_tax' => true, 'issue_date' => now()->toDateString()],
        [['product_id' => $product->id, 'size_id' => null, 'color_id' => null, 'quantity' => 2, 'unit_price' => 118, 'discount_percent' => 0]],
    );

    expect((float) $purchase->total)->toEqual(236.0)
        ->and((float) $product->variants()->first()->average_cost)->toEqual(118.0);
});

test('a draft purchase touches neither stock nor cash until it is confirmed', function () {
    $product = makeProduct(true);
    $service = app(PurchaseService::class);
    $header = ['document_type' => 'factura', 'prices_include_tax' => true, 'document_total' => 420, 'issue_date' => now()->toDateString()];
    $line = ['product_id' => $product->id, 'size_id' => null, 'color_id' => null, 'quantity' => 6, 'unit_price' => 70, 'discount_percent' => 0];

    $draft = $service->saveDraft($header, [$line]);

    expect($draft->isDraft())->toBeTrue()
        ->and((float) $draft->total)->toEqual(420.0)
        ->and($product->variants()->count())->toBe(0)
        ->and(PhysicalProduct::count())->toBe(0)
        ->and(CashMovement::count())->toBe(0);

    $purchase = $service->register($header, [$line], $draft);

    expect($purchase->id)->toBe($draft->id)
        ->and($purchase->status)->toBe(Purchase::STATUS_COMPLETED)
        ->and($purchase->draft_items)->toBeNull()
        ->and($product->variants()->first()->stock)->toBe(6)
        ->and((float) CashMovement::sole()->amount)->toEqual(420.0);

    // Ya confirmada, no vuelve a ser borrador.
    $service->saveDraft($header, [$line], $purchase);
})->throws(RuntimeException::class, 'ya fue confirmada');

test('the purchase form saves a draft, reopens it and confirms it', function () {
    $product = makeProduct(false);
    $product->update(['purchase_price' => 70]);

    $form = Livewire::test(PurchaseForm::class)
        ->call('addCreatedProduct', $product->id, 'purchase')
        ->set('document_type', 'factura')
        ->set('document_total', 140);

    $key = array_key_first($form->get('items'));
    $form->set("items.{$key}.quantity", 2)
        ->assertSet('documentDifference', 0.0)
        ->call('saveDraft')
        ->assertHasNoErrors();

    $draft = Purchase::sole();

    expect($draft->isDraft())->toBeTrue()
        ->and($form->get('draftId'))->toBe($draft->id)
        ->and($product->variants()->count())->toBe(0);

    Livewire::withQueryParams(['borrador' => $draft->id])
        ->test(PurchaseForm::class)
        ->assertSet('document_type', 'factura')
        ->assertSet('prices_include_tax', true)
        ->assertCount('items', 1)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('draftId', null);

    expect($draft->fresh()->status)->toBe(Purchase::STATUS_COMPLETED)
        ->and((float) $draft->fresh()->total)->toEqual(140.0)
        ->and($product->variants()->first()->stock)->toBe(2);
});

test('variant average cost is weighted across purchases', function () {
    $product = makeProduct(false);
    buy($product, 10, 20);
    buy($product, 10, 30);

    $variant = $product->variants()->first();

    expect($variant->stock)->toBe(20)
        ->and((float) $variant->average_cost)->toEqual(25.0);
});

test('sale prices include IGV and copy the unit cost for the margin', function () {
    $product = makeProduct(false);
    buy($product, 10, 50);

    $sale = sell($product, 2, 118);
    $detail = $sale->details->first();

    expect((float) $sale->total)->toEqual(236.0)
        ->and((float) $sale->subtotal)->toEqual(200.0)
        ->and((float) $sale->tax)->toEqual(36.0)
        ->and((float) $detail->unit_price)->toEqual(100.0)
        ->and((float) $detail->subtotal)->toEqual(200.0)
        ->and((float) $detail->unit_cost)->toEqual(50.0);
});

test('an edited line total wins over quantity times unit price', function () {
    $product = makeProduct(false);
    buy($product, 10, 50);

    $sale = sell($product, 3, 100, lineTotal: 250);

    expect((float) $sale->total)->toEqual(250.0)
        ->and((float) $sale->details->first()->subtotal)->toEqual(round(250 / 1.18, 2));
});

test('selling a chosen physical unit uses that unit and its own cost', function () {
    $product = makeProduct(true);
    buy($product, 1, 100);
    buy($product, 1, 200);
    $expensive = PhysicalProduct::where('purchase_cost', 200)->first();

    $sale = sell($product, 1, 500, physicalProductId: $expensive->id);

    expect($sale->details->first()->physical_product_id)->toBe($expensive->id)
        ->and((float) $sale->details->first()->unit_cost)->toEqual(200.0)
        ->and($expensive->fresh()->status)->toBe('sold')
        ->and(PhysicalProduct::where('status', 'available')->count())->toBe(1);
});

test('renting a chosen physical unit rents exactly that unit', function () {
    $product = makeProduct(true);
    buy($product, 3, 100);
    $chosen = PhysicalProduct::orderByDesc('id')->first();

    app(RentalService::class)->register(
        ['client_id' => createClient()->id, 'pickup_date' => now(), 'expected_return_date' => now()->addDay()],
        [['product_id' => $product->id, 'physical_product_id' => $chosen->id, 'quantity' => 1, 'unit_price' => 80]],
    );

    expect($chosen->fresh()->status)->toBe('rented')
        ->and(PhysicalProduct::where('status', 'available')->count())->toBe(2);
});

test('a sale only purchase can be reduced while stock stays non negative', function () {
    $product = makeProduct(false);
    $purchase = buy($product, 10, 20);
    sell($product, 5, 50);
    $detail = $purchase->details->first();

    app(PurchaseService::class)->reduceLineQuantity($detail, 5);

    expect($product->variants()->first()->stock)->toBe(0)
        ->and($detail->fresh()->quantity)->toBe(5)
        ->and((float) $purchase->fresh()->subtotal)->toEqual(100.0);

    app(PurchaseService::class)->reduceLineQuantity($detail->fresh(), 4);
})->throws(RuntimeException::class, 'negativo');

test('a rentable purchase only removes never operated units', function () {
    $product = makeProduct(true);
    $purchase = buy($product, 5, 100);
    $detail = $purchase->details->first();
    $units = $detail->physicalProducts()->orderBy('id')->get();

    // Dos prendas ya se alquilaron alguna vez (aunque hoy estén disponibles).
    foreach ($units->take(2) as $unit) {
        app(RentalService::class)->register(
            ['client_id' => createClient()->id, 'pickup_date' => now(), 'expected_return_date' => now()->addDay()],
            [['product_id' => $product->id, 'physical_product_id' => $unit->id, 'quantity' => 1, 'unit_price' => 80]],
        );
        $unit->update(['status' => 'available']);
    }

    app(PurchaseService::class)->reduceLineQuantity($detail, 3);

    expect($detail->physicalProducts()->count())->toBe(3)
        ->and($detail->physicalProducts()->whereKey($units->take(2)->pluck('id'))->count())->toBe(2)
        ->and($product->variants()->first()->stock)->toBe(3);

    // Solo queda 1 prenda sin operar: no se pueden quitar 2 más.
    app(PurchaseService::class)->reduceLineQuantity($detail->fresh(), 1);
})->throws(RuntimeException::class, 'nunca se han operado');

test('a rentable purchase cannot be reduced when every unit was operated', function () {
    $product = makeProduct(true);
    $purchase = buy($product, 2, 100);
    $detail = $purchase->details->first();

    foreach ($detail->physicalProducts as $unit) {
        app(RentalService::class)->register(
            ['client_id' => createClient()->id, 'pickup_date' => now(), 'expected_return_date' => now()->addDay()],
            [['product_id' => $product->id, 'physical_product_id' => $unit->id, 'quantity' => 1, 'unit_price' => 80]],
        );
    }

    expect(app(PurchaseService::class)->removableQuantity($detail))->toBe(0);

    app(PurchaseService::class)->reduceLineQuantity($detail, 1);
})->throws(RuntimeException::class);

test('purchase header can be edited without touching stock', function () {
    $product = makeProduct(false);
    $purchase = buy($product, 4, 20);

    Livewire::test(PurchaseDetailModal::class)
        ->call('loadPurchase', $purchase->id)
        ->set('document_number', 'F001-000777')
        ->set('issue_date', '2026-09-01')
        ->set('has_invoice_pending', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('purchaseUpdated');

    $purchase->refresh();

    expect($purchase->document_number)->toBe('F001-000777')
        ->and($purchase->issue_date->toDateString())->toBe('2026-09-01')
        ->and($purchase->has_invoice_pending)->toBeTrue()
        ->and($product->variants()->first()->stock)->toBe(4);
});

test('purchase modal reduces a line quantity and reports errors', function () {
    $product = makeProduct(false);
    $purchase = buy($product, 10, 20);
    sell($product, 8, 50);
    $detailId = $purchase->details->first()->id;

    Livewire::test(PurchaseDetailModal::class)
        ->call('loadPurchase', $purchase->id)
        ->set("quantities.{$detailId}", 7)
        ->call('reduceQuantity', $detailId)
        ->assertHasErrors("quantities.{$detailId}")
        ->set("quantities.{$detailId}", 8)
        ->call('reduceQuantity', $detailId)
        ->assertHasNoErrors();

    expect($purchase->details->first()->fresh()->quantity)->toBe(8);
});

test('gallery opens the product picker for every product', function () {
    $rentable = makeProduct(true);
    $saleOnly = makeProduct(false);

    Livewire::test(ProductGallery::class, ['mode' => 'sale', 'fieldContext' => 'sale-gallery'])
        ->call('addToCart', $rentable->id)
        ->assertDispatched('product-picker-requested', productId: $rentable->id)
        ->call('addToCart', $saleOnly->id)
        ->assertDispatched('product-picker-requested', productId: $saleOnly->id);
});

test('picked units are added once to the rental and sale carts', function () {
    $product = makeProduct(true);
    buy($product, 2, 100);
    $variant = $product->variants()->first();
    $unitIds = PhysicalProduct::pluck('id')->all();

    $picker = Livewire::test(ProductPicker::class)
        ->call('open', 'rental-gallery', $product->id, 'rental')
        ->assertSet('variantId', $variant->id)
        ->call('toggleUnit', $unitIds[0])
        ->call('addSelected')
        ->assertDispatched('product-picker-selected');

    $items = $picker->effects['dispatches'][0]['params']['items'];

    $rentalForm = Livewire::test(RentalForm::class)
        ->call('handlePickedItems', 'rental-gallery', $items)
        ->call('handlePickedItems', 'rental-gallery', $items);

    expect(array_values($rentalForm->get('items')))->toHaveCount(1)
        ->and(array_values($rentalForm->get('items'))[0]['physical_product_id'])->toBe($unitIds[0]);

    $saleForm = Livewire::test(SaleForm::class)->call('handlePickedItems', 'sale-gallery', $items);

    expect(array_values($saleForm->get('items'))[0]['line_total'])->toEqual($items[0]['unit_price']);
});

test('sale only products are picked by variant with stock as the limit', function () {
    $product = makeProduct(false);
    buy($product, 3, 20);
    $variant = $product->variants()->first();
    $variant->update(['sale_price' => 59]);

    $picker = Livewire::test(ProductPicker::class)
        ->call('open', 'sale-gallery', $product->id, 'sale')
        ->assertSet('variantId', $variant->id)
        ->set('quantity', 4)
        ->call('addSelected')
        ->assertHasErrors('quantity')
        ->set('quantity', 2)
        ->call('addSelected')
        ->assertHasNoErrors()
        ->assertDispatched('product-picker-selected');

    $items = $picker->effects['dispatches'][0]['params']['items'];

    expect($items[0]['physical_product_id'])->toBeNull()
        ->and($items[0]['quantity'])->toBe(2)
        ->and($items[0]['unit_price'])->toEqual(59.0);

    // Elegir la misma variante otra vez suma unidades a la misma fila.
    $saleForm = Livewire::test(SaleForm::class)
        ->call('handlePickedItems', 'sale-gallery', $items)
        ->call('handlePickedItems', 'sale-gallery', $items);

    $rows = array_values($saleForm->get('items'));

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['quantity'])->toBe(4)
        ->and($rows[0]['line_total'])->toEqual(236.0);
});

test('variants without stock cannot be picked', function () {
    $product = makeProduct(false);
    buy($product, 1, 20);
    $variant = $product->variants()->first();
    $variant->update(['stock' => 0]);

    Livewire::test(ProductPicker::class)
        ->call('open', 'sale-gallery', $product->id, 'sale')
        ->assertSet('variantId', null)
        ->call('selectVariant', $variant->id)
        ->assertSet('variantId', null)
        ->assertSee('Sin disponibilidad');
});
