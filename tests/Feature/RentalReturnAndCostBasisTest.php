<?php

use App\Livewire\Inventory\PhysicalItemTracker;
use App\Livewire\Rental\RentalReturn;
use App\Livewire\Sale\SaleForm;
use App\Models\CashMovement;
use App\Models\InventoryMovement;
use App\Models\PhysicalProduct;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Rental;
use App\Services\Inventory\ItemTrackingService;
use App\Services\Purchasing\PurchaseService;
use App\Services\Rental\RentalService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->seller = createSeller();
    $this->actingAs($this->seller);
});

function purchaseProduct(bool $rentable, int $quantity, float $unitPrice, string $documentType): Purchase
{
    $code = Str::random(6);
    $product = Product::create([
        'code' => $code, 'name' => "Producto {$code}", 'slug' => Str::slug($code),
        'allow_sale' => true, 'allow_rental' => $rentable,
    ]);

    return app(PurchaseService::class)->register(
        ['document_type' => $documentType, 'issue_date' => now()->toDateString()],
        [['product_id' => $product->id, 'size_id' => null, 'color_id' => null, 'quantity' => $quantity, 'unit_price' => $unitPrice]],
    );
}

function rentAllUnits(Purchase $purchase, float $deposit = 0): Rental
{
    $detail = $purchase->details->first();

    return app(RentalService::class)->register(
        ['client_id' => createClient()->id, 'pickup_date' => now()->subDays(2), 'expected_return_date' => now()->subDay(), 'deposit_amount' => $deposit],
        $detail->physicalProducts->map(fn ($unit) => [
            'product_id' => $detail->variant->product_id, 'physical_product_id' => $unit->id, 'quantity' => 1, 'unit_price' => 100,
        ])->all(),
    );
}

// ---------------- Costo según comprobante ----------------

test('with an invoice the cost is the price without IGV', function () {
    $purchase = purchaseProduct(true, 2, 100, 'factura');

    expect(PhysicalProduct::pluck('purchase_cost')->map(fn ($c) => (float) $c)->unique()->all())->toBe([100.0])
        ->and((float) $purchase->details->first()->variant->average_cost)->toEqual(100.0);
});

test('without an invoice the cost is the full payment with IGV', function (string $documentType) {
    $purchase = purchaseProduct(true, 2, 100, $documentType);

    expect(PhysicalProduct::pluck('purchase_cost')->map(fn ($c) => (float) $c)->unique()->all())->toBe([118.0])
        ->and((float) $purchase->details->first()->variant->average_cost)->toEqual(118.0);
})->with(['boleta', 'nota_venta']);

test('changing a purchase to invoice recalculates the cost of its units', function () {
    $purchase = purchaseProduct(true, 2, 100, 'boleta');

    app(PurchaseService::class)->updateHeader($purchase, ['document_type' => 'factura', 'document_number' => 'F001-1']);

    $variant = $purchase->details->first()->variant->fresh();

    expect(PhysicalProduct::pluck('purchase_cost')->map(fn ($c) => (float) $c)->unique()->all())->toBe([100.0])
        ->and((float) $variant->average_cost)->toEqual(100.0)
        ->and($purchase->fresh()->document_number)->toBe('F001-1');
});

// ---------------- Fecha de baja ----------------

test('disposal date is set when a unit is written off and cleared if recovered', function () {
    $unit = createPhysicalUnit(createProductIn(null));

    $unit->update(['status' => 'stolen']);
    expect($unit->fresh()->disposed_at)->not->toBeNull();

    $unit->update(['status' => 'available']);
    expect($unit->fresh()->disposed_at)->toBeNull();
});

// ---------------- Recepción del alquiler ----------------

test('receiving a rental settles each unit and records the damage fee as income', function () {
    $purchase = purchaseProduct(true, 3, 100, 'factura');
    $rental = rentAllUnits($purchase, deposit: 50);
    [$good, $damaged, $lost] = $rental->details->all();
    $variant = $purchase->details->first()->variant;

    app(RentalService::class)->receive($rental, [
        'actual_return_date' => now()->toDateTimeString(),
        'return_notes' => 'El cliente discutió el cobro.',
        'items' => [
            $good->id => ['condition' => 'good', 'damage_fee' => 0, 'next_status' => 'available'],
            $damaged->id => ['condition' => 'damaged', 'damage_fee' => 40, 'damage_note' => 'Solapa rota', 'next_status' => 'maintenance'],
            $lost->id => ['condition' => 'lost', 'damage_fee' => 300, 'next_status' => 'available'],
        ],
    ]);

    $rental->refresh();

    expect($rental->status)->toBe(Rental::STATUS_RETURNED)
        ->and($rental->actual_return_date)->not->toBeNull()
        ->and($rental->return_notes)->toBe('El cliente discutió el cobro.')
        ->and((float) $rental->penalty_total)->toEqual(340.0)
        ->and($good->physicalProduct->fresh()->status)->toBe('available')
        ->and($damaged->physicalProduct->fresh()->status)->toBe('maintenance')
        ->and($lost->physicalProduct->fresh()->status)->toBe('lost')
        ->and($lost->physicalProduct->fresh()->disposed_at)->not->toBeNull()
        ->and($variant->fresh()->stock)->toBe(2)
        ->and(InventoryMovement::where('concept', InventoryMovement::CONCEPT_LOST)->count())->toBe(1)
        ->and((float) CashMovement::where('origin', CashMovement::ORIGIN_PENALTY_FEE)->sum('amount'))->toEqual(340.0);

    // El cobro por daño suma al resultado de la prenda.
    $history = app(ItemTrackingService::class)->unitHistory($damaged->physicalProduct->fresh());

    expect($history['damage_income'])->toEqual(40.0)
        ->and($history['profit'])->toEqual(40.0); // 100 alquiler + 40 daño − 100 costo
});

test('a rental can only be received once', function () {
    $rental = rentAllUnits(purchaseProduct(true, 1, 100, 'factura'));
    $data = ['actual_return_date' => now(), 'items' => [$rental->details->first()->id => ['condition' => 'good']]];

    app(RentalService::class)->receive($rental, $data);
    app(RentalService::class)->receive($rental, $data);
})->throws(RuntimeException::class, 'no está en curso');

test('return screen receives the rental and offers to sell a returned unit', function () {
    $rental = rentAllUnits(purchaseProduct(true, 1, 100, 'factura'));
    $detail = $rental->details->first();

    $this->get(route('rental.rentals.return', $rental))->assertOk();

    Livewire::test(RentalReturn::class, ['rental' => $rental])
        ->set("items.{$detail->id}.condition", 'damaged')
        ->assertSet("items.{$detail->id}.next_status", 'maintenance')
        ->set("items.{$detail->id}.condition", 'good')
        ->assertSet("items.{$detail->id}.next_status", 'available')
        ->set('return_notes', 'Todo en orden')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('completed', true)
        ->assertSee(route('sale.sales.create', ['prenda' => $detail->physical_product_id]));
});

test('the sale form preloads an available unit to sell it', function () {
    $purchase = purchaseProduct(true, 1, 100, 'factura');
    $unit = $purchase->details->first()->physicalProducts->first();

    $items = array_values(Livewire::withQueryParams(['prenda' => $unit->id])->test(SaleForm::class)->get('items'));

    expect($items)->toHaveCount(1)
        ->and($items[0]['physical_product_id'])->toBe($unit->id);

    $unit->update(['status' => 'rented']);

    expect(Livewire::withQueryParams(['prenda' => $unit->id])->test(SaleForm::class)->get('items'))->toBe([]);
});

test('a unit in maintenance can be marked available from the tracker', function () {
    $unit = createPhysicalUnit(createProductIn(null), status: 'maintenance');

    Livewire::actingAs(createSeller())
        ->test(PhysicalItemTracker::class)
        ->call('selectUnit', $unit->id)
        ->call('markUnitAvailable')
        ->assertForbidden();

    Livewire::actingAs(createAdmin())
        ->test(PhysicalItemTracker::class)
        ->call('selectUnit', $unit->id)
        ->call('markUnitAvailable');

    expect($unit->fresh()->status)->toBe('available');
});
