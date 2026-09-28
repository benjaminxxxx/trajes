<?php

use App\Livewire\Rental\RentalDetailModal;
use App\Livewire\Rental\RentalForm;
use App\Livewire\Sale\SaleDetailModal;
use App\Livewire\Sale\SaleForm;
use App\Livewire\Shared\CategoryYearSummary;
use App\Models\CashMovement;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Rental;
use App\Models\Sale;
use App\Models\User;
use App\Services\Inventory\CategoryReportService;
use App\Services\Rental\RentalService;
use App\Services\Sale\SaleService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->seller = User::factory()->create();
    $this->seller->assignRole('vendedor');

    $this->actingAs($this->admin);
});

function registerSale(int $quantity = 2, ?Category $category = null): Sale
{
    $variant = createProductIn($category, stock: 5);
    createPhysicalUnit($variant);

    return app(SaleService::class)->register(
        ['document_type' => 'nota_venta', 'has_invoice_pending' => true, 'sale_date' => now()->toDateString()],
        [['product_id' => $variant->product_id, 'size_id' => null, 'color_id' => null, 'quantity' => $quantity, 'unit_price' => 100, 'discount_percent' => 0]],
    );
}

function registerRental(float $deposit = 50): Rental
{
    $variant = createProductIn(null);
    createPhysicalUnit($variant);

    return app(RentalService::class)->register(
        ['client_id' => createClient()->id, 'pickup_date' => now(), 'expected_return_date' => now()->addDay(), 'deposit_amount' => $deposit],
        [['product_id' => $variant->product_id, 'size_id' => null, 'color_id' => null, 'quantity' => 1, 'unit_price' => 80]],
    );
}

test('sales and rentals pages render with homogeneous routes', function () {
    registerSale();
    registerRental();

    $this->get(route('sale.sales.index'))->assertOk()->assertSee('V-');
    $this->get(route('rental.rentals.index'))->assertOk()->assertSee('A-');
    $this->get(route('sale.sales.create'))->assertOk();
    $this->get(route('rental.rentals.create'))->assertOk();
});

test('cancelling a sale restores stock and keeps the record', function () {
    $sale = registerSale(quantity: 2);
    $detail = $sale->details->first();

    expect($detail->variant->fresh()->stock)->toBe(3)
        ->and($detail->physicalProduct->fresh()->status)->toBe('sold');

    Livewire::test(SaleDetailModal::class)
        ->call('loadSale', $sale->id)
        ->set('cancellationReason', 'Talla equivocada')
        ->call('cancel')
        ->assertHasNoErrors()
        ->assertDispatched('saleUpdated');

    $sale->refresh();

    expect($sale->status)->toBe(Sale::STATUS_CANCELLED)
        ->and($sale->cancelled_by)->toBe($this->admin->id)
        ->and($sale->cancellation_reason)->toBe('Talla equivocada')
        ->and($sale->trashed())->toBeFalse()
        ->and($detail->variant->fresh()->stock)->toBe(5)
        ->and($detail->physicalProduct->fresh()->status)->toBe('available');

    expect(InventoryMovement::where('concept', InventoryMovement::CONCEPT_SALE_CANCELLED)->sum('quantity'))->toEqual(2)
        ->and(CashMovement::where('reference_type', Sale::class)->where('type', CashMovement::TYPE_EXPENSE)->sum('amount'))->toEqual($sale->total);
});

test('a sale cannot be cancelled twice', function () {
    $sale = registerSale();
    app(SaleService::class)->cancel($sale, 'Primera anulación');

    app(SaleService::class)->cancel($sale, 'Segunda anulación');
})->throws(RuntimeException::class);

test('only admins can cancel a sale', function () {
    $sale = registerSale();

    Livewire::actingAs($this->seller)
        ->test(SaleDetailModal::class)
        ->call('loadSale', $sale->id)
        ->set('cancellationReason', 'Talla equivocada')
        ->call('cancel')
        ->assertForbidden();

    expect($sale->fresh()->status)->toBe(Sale::STATUS_COMPLETED);
});

test('editing a sale only changes non stock data', function () {
    $sale = registerSale();
    $client = createClient();

    Livewire::actingAs($this->admin)
        ->test(SaleDetailModal::class)
        ->call('loadSale', $sale->id)
        ->set('client_id', $client->id)
        ->set('sale_date', '2026-01-15')
        ->set('notes', 'Corregido')
        ->call('save')
        ->assertHasNoErrors();

    $sale->refresh();

    expect($sale->client_id)->toBe($client->id)
        ->and($sale->sale_date->toDateString())->toBe('2026-01-15')
        ->and($sale->notes)->toBe('Corregido')
        ->and($sale->details->first()->variant->stock)->toBe(3);
});

test('a cancelled sale cannot be edited', function () {
    $sale = registerSale();
    app(SaleService::class)->cancel($sale, 'Anulada');

    Livewire::test(SaleDetailModal::class)
        ->call('loadSale', $sale->id)
        ->set('notes', 'Intento de edición')
        ->call('save')
        ->assertForbidden();
});

test('invoicing a sale stores the document and clears the pending flag', function () {
    $sale = registerSale();

    Livewire::actingAs($this->admin)
        ->test(SaleDetailModal::class)
        ->call('loadSale', $sale->id)
        ->set('document_type', 'factura')
        ->set('document_number', 'F001-000045')
        ->set('invoice_date', '2026-09-20')
        ->call('invoice')
        ->assertHasNoErrors();

    $sale->refresh();

    expect($sale->has_invoice_pending)->toBeFalse()
        ->and($sale->document_type)->toBe('factura')
        ->and($sale->document_number)->toBe('F001-000045')
        ->and($sale->invoice_date->toDateString())->toBe('2026-09-20');
});

test('invoicing requires a boleta or factura with its number', function () {
    $sale = registerSale();

    Livewire::test(SaleDetailModal::class)
        ->call('loadSale', $sale->id)
        ->set('document_type', 'nota_venta')
        ->set('document_number', '')
        ->call('invoice')
        ->assertHasErrors(['document_type', 'document_number']);

    expect($sale->fresh()->has_invoice_pending)->toBeTrue();
});

test('cancelling a rental releases its garments and refunds the deposit', function () {
    $rental = registerRental(deposit: 50);
    $unit = $rental->details->first()->physicalProduct;

    expect($unit->fresh()->status)->toBe('rented');

    Livewire::test(RentalDetailModal::class)
        ->call('loadRental', $rental->id)
        ->set('cancellationReason', 'Cliente equivocado')
        ->call('cancel')
        ->assertHasNoErrors()
        ->assertDispatched('rentalUpdated');

    $rental->refresh();

    expect($rental->status)->toBe(Rental::STATUS_CANCELLED)
        ->and($rental->trashed())->toBeFalse()
        ->and($unit->fresh()->status)->toBe('available')
        ->and(CashMovement::where('reference_type', Rental::class)->where('type', CashMovement::TYPE_EXPENSE)->sum('amount'))->toEqual(50);
});

test('a returned rental cannot be cancelled', function () {
    $rental = registerRental();
    $rental->update(['status' => Rental::STATUS_RETURNED]);

    app(RentalService::class)->cancel($rental, 'Tarde');
})->throws(RuntimeException::class);

test('editing a rental updates its dates and client', function () {
    $rental = registerRental();
    $client = createClient();

    Livewire::actingAs($this->admin)
        ->test(RentalDetailModal::class)
        ->call('loadRental', $rental->id)
        ->set('client_id', $client->id)
        ->set('expected_return_date', now()->addDays(3)->format('Y-m-d\TH:i'))
        ->call('save')
        ->assertHasNoErrors();

    expect($rental->fresh()->client_id)->toBe($client->id);
});

test('a cancelled sale can be redone from the sale form', function () {
    $sale = registerSale(quantity: 2);
    app(SaleService::class)->cancel($sale, 'Anulada');

    $component = Livewire::withQueryParams(['from' => $sale->id])->test(SaleForm::class);

    $component->assertSet('redoFromCode', $sale->code);

    $item = array_values($component->get('items'))[0];

    expect($item['product_id'])->toBe($sale->details->first()->variant->product_id)
        ->and($item['quantity'])->toBe(2);
});

test('a completed sale is not used to prefill the form', function () {
    $sale = registerSale();

    Livewire::withQueryParams(['from' => $sale->id])
        ->test(SaleForm::class)
        ->assertSet('redoFromCode', null)
        ->assertSet('items', []);
});

test('a cancelled rental can be redone from the rental form', function () {
    $rental = registerRental();
    app(RentalService::class)->cancel($rental, 'Anulado');

    $component = Livewire::withQueryParams(['from' => $rental->id])->test(RentalForm::class);

    $component->assertSet('redoFromCode', $rental->code)
        ->assertSet('client_id', $rental->client_id);

    expect(array_values($component->get('items'))[0]['quantity'])->toBe(1);
});

test('yearly summary groups by category and month excluding cancelled operations', function () {
    $category = Category::create(['name' => 'Ternos', 'slug' => 'ternos']);
    $variant = createProductIn($category);

    sellVariant($variant, '2026-02-10', quantity: 3);
    sellVariant($variant, '2026-02-11', quantity: 4, status: 'cancelled');
    rentVariant($variant, '2026-04-05');

    $service = app(CategoryReportService::class);

    $sales = $service->categoryMonthMatrix('sales', 2026, 'units');
    $salesAmount = $service->categoryMonthMatrix('sales', 2026, 'amount');
    $rentals = $service->categoryMonthMatrix('rentals', 2026, 'units');

    expect($sales['Ternos']['months'][2])->toEqual(3)
        ->and($sales['Ternos']['total'])->toEqual(3)
        ->and($salesAmount['Ternos']['total'])->toEqual(60)
        ->and($rentals['Ternos']['months'][4])->toEqual(1);

    Livewire::test(CategoryYearSummary::class, ['type' => 'sales'])
        ->call('open')
        ->assertSee('Ternos');
});
