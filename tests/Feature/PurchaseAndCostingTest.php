<?php

use App\Livewire\Purchasing\PurchaseDetailModal;
use App\Livewire\Rental\RentalForm;
use App\Livewire\Sale\SaleForm;
use App\Livewire\Shared\ProductGallery;
use App\Livewire\Shared\ProductPicker;
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
