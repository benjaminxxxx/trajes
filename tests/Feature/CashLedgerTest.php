<?php

use App\Livewire\Finance\CashIndex;
use App\Livewire\Finance\ExpenseIndex;
use App\Models\CashMovement;
use App\Models\ItemMaintenance;
use App\Models\OperationalExpense;
use App\Models\ProductVariant;
use App\Services\Assets\AssetService;
use App\Services\Core\DashboardService;
use App\Services\Finance\CashService;
use App\Services\Finance\ExpenseService;
use App\Services\Maintenance\MaintenanceService;
use App\Services\Purchasing\PurchaseService;
use App\Services\Rental\RentalService;
use App\Services\Sale\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(createAdmin());
    $this->cash = app(CashService::class);
});

function sellableVariant(int $stock = 5): ProductVariant
{
    $variant = createProductIn(null, stock: $stock);
    $variant->product->update(['allow_rental' => false]);

    return $variant;
}

function saleLine(ProductVariant $variant, int $quantity, float $price): array
{
    return ['product_id' => $variant->product_id, 'size_id' => null, 'color_id' => null, 'quantity' => $quantity, 'unit_price' => $price, 'discount_percent' => 0];
}

// ---------------- Movimientos manuales ----------------

test('injecting capital and withdrawing money move the cash balance', function () {
    Livewire::test(CashIndex::class)
        ->call('openForm', CashMovement::ORIGIN_CAPITAL_INJECTION)
        ->set('amount', 10000)
        ->set('description', 'Capital campaña de diciembre')
        ->call('save')
        ->assertHasNoErrors()
        ->call('openForm', CashMovement::ORIGIN_WITHDRAWAL)
        ->set('amount', 1500.50)
        ->set('description', 'Retiro personal')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Capital campaña de diciembre')
        ->assertSeeInOrder(['Saldo en caja', '8,499.50']);

    expect($this->cash->balance())->toBe(8499.5)
        ->and(CashMovement::where('origin', CashMovement::ORIGIN_CAPITAL_INJECTION)->sole()->type)->toBe(CashMovement::TYPE_INCOME)
        ->and(CashMovement::where('origin', CashMovement::ORIGIN_WITHDRAWAL)->sole()->type)->toBe(CashMovement::TYPE_EXPENSE);
});

test('a manual movement needs a positive amount and a reason', function () {
    Livewire::test(CashIndex::class)
        ->call('openForm')
        ->set('amount', 0)
        ->set('description', '')
        ->call('save')
        ->assertHasErrors(['amount', 'description']);

    expect(CashMovement::count())->toBe(0);
});

test('a mistake is undone with the inverse movement and the history stays', function () {
    $movement = $this->cash->registerManual(CashMovement::ORIGIN_CAPITAL_INJECTION, 500, 'Prueba');

    Livewire::test(CashIndex::class)
        ->call('confirmReverse', $movement->id)
        ->call('reverse')
        ->assertHasErrors('reverseReason')
        ->set('reverseReason', 'Era una prueba')
        ->call('reverse')
        ->assertHasNoErrors();

    $reversal = CashMovement::where('reverses_id', $movement->id)->sole();

    expect($this->cash->balance())->toBe(0.0)
        ->and(CashMovement::count())->toBe(2)
        ->and($reversal->type)->toBe(CashMovement::TYPE_EXPENSE)
        ->and((float) $reversal->amount)->toBe(500.0)
        ->and($reversal->description)->toContain('Era una prueba');

    // Ni el original ni su inverso se pueden volver a anular.
    expect(fn () => $this->cash->reverse($movement, 'Otra vez'))->toThrow(RuntimeException::class)
        ->and(fn () => $this->cash->reverse($reversal, 'Deshacer'))->toThrow(RuntimeException::class)
        ->and(CashMovement::count())->toBe(2);
});

test('cash movements cannot be edited or deleted', function () {
    $movement = $this->cash->registerManual(CashMovement::ORIGIN_CAPITAL_INJECTION, 500, 'Aporte');

    expect(fn () => $movement->update(['amount' => 1]))->toThrow(LogicException::class)
        ->and(fn () => $movement->delete())->toThrow(LogicException::class)
        ->and((float) $movement->fresh()->amount)->toBe(500.0);
});

test('a movement born from an operation is not reversed from the cash screen', function () {
    $sale = app(SaleService::class)->register(
        ['document_type' => 'nota_venta', 'sale_date' => now()->toDateString()],
        [saleLine(sellableVariant(), 1, 100)],
    );

    $movement = CashMovement::where('origin', CashMovement::ORIGIN_SALE_PAYMENT)->sole();

    expect(fn () => $this->cash->reverse($movement, 'Error'))->toThrow(RuntimeException::class)
        ->and($this->cash->balance())->toBe((float) $sale->total);
});

// ---------------- Cada operación con dinero mueve la caja ----------------

test('a sale fills the cash and cancelling it takes the money back out', function () {
    $sale = app(SaleService::class)->register(
        ['document_type' => 'nota_venta', 'sale_date' => now()->toDateString()],
        [saleLine(sellableVariant(), 2, 100)],
    );

    expect($this->cash->balance())->toBe(200.0);

    app(SaleService::class)->cancel($sale, 'Talla equivocada');

    expect($this->cash->balance())->toBe(0.0)
        ->and(CashMovement::count())->toBe(2);
});

test('a purchase takes its total out of the cash and a quantity correction returns the difference', function () {
    $this->cash->registerManual(CashMovement::ORIGIN_CAPITAL_INJECTION, 10000, 'Capital');
    $variant = sellableVariant(0);

    $purchase = app(PurchaseService::class)->register(
        ['document_type' => 'factura', 'issue_date' => now()->toDateString()],
        [saleLine($variant, 10, 100)],
    );

    // 10 × 100 + IGV = 1,180 pagados al proveedor.
    expect($this->cash->balance())->toBe(8820.0)
        ->and(CashMovement::where('origin', CashMovement::ORIGIN_PURCHASE_EXPENSE)->sole()->reference->is($purchase))->toBeTrue();

    app(PurchaseService::class)->reduceLineQuantity($purchase->details->first(), 4);

    // Quedan 4 × 100 + IGV = 472 pagados.
    expect($this->cash->balance())->toBe(9528.0);
});

test('an expense leaves the cash and cancelling it brings the money back without deleting it', function () {
    $expense = app(ExpenseService::class)->register([
        'category' => 'Servicios', 'concept' => 'Luz', 'amount' => 45, 'expense_date' => now(),
    ]);

    expect($this->cash->balance())->toBe(-45.0);

    Livewire::test(ExpenseIndex::class)
        ->call('confirmCancel', $expense->id)
        ->call('cancel')
        ->assertHasErrors('cancellationReason')
        ->set('cancellationReason', 'Registrado por error')
        ->call('cancel')
        ->assertHasNoErrors()
        ->assertSee('Anulado');

    $expense->refresh();
    $stats = app(DashboardService::class)->periodStats(now()->startOfMonth(), now()->endOfMonth());

    expect($this->cash->balance())->toBe(0.0)
        ->and($expense->isCancelled())->toBeTrue()
        ->and($expense->cancellation_reason)->toBe('Registrado por error')
        ->and(CashMovement::where('reference_type', $expense->getMorphClass())->count())->toBe(2)
        ->and($stats['expenses'])->toEqual(0.0);

    expect(fn () => app(ExpenseService::class)->cancel($expense, 'Otra vez'))->toThrow(RuntimeException::class)
        ->and($this->cash->balance())->toBe(0.0);
});

test('expenses cannot be deleted nor have their amount changed', function () {
    $expense = app(ExpenseService::class)->register([
        'category' => 'Servicios', 'concept' => 'Agua', 'amount' => 30, 'expense_date' => now(),
    ]);

    expect(fn () => $expense->delete())->toThrow(LogicException::class)
        ->and(fn () => $expense->update(['amount' => 1]))->toThrow(LogicException::class)
        ->and(OperationalExpense::count())->toBe(1);
});

test('a rental brings in the rent and the deposit, and the deposit leaves when the garment returns', function () {
    $variant = createProductIn(null);
    createPhysicalUnit($variant);

    $rental = app(RentalService::class)->register(
        ['client_id' => createClient()->id, 'pickup_date' => now(), 'expected_return_date' => now()->addDay(), 'deposit_amount' => 50],
        [['product_id' => $variant->product_id, 'size_id' => null, 'color_id' => null, 'quantity' => 1, 'unit_price' => 80]],
    );

    expect($this->cash->balance())->toBe(130.0);

    app(RentalService::class)->receive($rental, [
        'actual_return_date' => now()->toDateTimeString(),
        'items' => [$rental->details->first()->id => ['condition' => 'damaged', 'damage_fee' => 20, 'next_status' => 'available']],
    ]);

    // Queda el alquiler (80) + el cobro por daños (20); la garantía volvió al cliente.
    expect($this->cash->balance())->toBe(100.0)
        ->and((float) CashMovement::where('origin', CashMovement::ORIGIN_RENTAL_DEPOSIT)->where('type', CashMovement::TYPE_EXPENSE)->sum('amount'))->toBe(50.0);
});

test('a maintenance cost leaves the cash', function () {
    $unit = createPhysicalUnit(createProductIn(null));

    app(MaintenanceService::class)->register($unit, [
        'type' => array_key_first(ItemMaintenance::TYPES), 'cost' => 25, 'maintenance_date' => now()->toDateString(),
    ]);

    expect($this->cash->balance())->toBe(-25.0)
        ->and(CashMovement::sole()->origin)->toBe(CashMovement::ORIGIN_MAINTENANCE);
});

test('buying goods leaves the cash only when it is paid from it', function () {
    $service = app(AssetService::class);

    $owned = $service->create(['name' => 'Escritorio', 'type' => 'mobiliario', 'quantity' => 1, 'unit_cost' => 450]);

    expect($this->cash->balance())->toBe(0.0);

    $service->create(['name' => 'Maniquí', 'type' => 'exhibicion', 'quantity' => 2, 'unit_cost' => 100], paidFromCash: true);
    $service->addStock($owned, 1, 400, paidFromCash: true);

    expect($this->cash->balance())->toBe(-600.0)
        ->and(CashMovement::where('origin', CashMovement::ORIGIN_ASSET_PURCHASE)->count())->toBe(2);
});

// ---------------- Pantalla y permisos ----------------

test('the bank balance is compared with the cash', function (float $bank, string $message) {
    $this->cash->registerManual(CashMovement::ORIGIN_CAPITAL_INJECTION, 1000, 'Capital');

    Livewire::test(CashIndex::class)
        ->set('bankBalance', $bank)
        ->assertSee($message);
})->with([
    'matches' => [1000, 'Cuadra'],
    'bank is short' => [900, 'Faltan S/ 100.00 en el banco'],
    'bank has more' => [1250, 'Sobran S/ 250.00 en el banco'],
]);

test('only people with access see and use the cash', function () {
    $this->get(route('finance.cash.index'))->assertOk();

    $this->actingAs(createSeller());

    $this->get(route('finance.cash.index'))->assertForbidden();

    Livewire::test(CashIndex::class)
        ->set('amount', 100)
        ->set('description', 'Aporte no autorizado')
        ->call('save')
        ->assertForbidden();

    expect(CashMovement::count())->toBe(0);
});

test('the cash of one business does not include another business', function () {
    $this->cash->registerManual(CashMovement::ORIGIN_CAPITAL_INJECTION, 700, 'Capital');

    $other = createTestAccount('Otro negocio');
    actingAsIn(createAdmin($other), $other);

    expect(app(CashService::class)->balance())->toBe(0.0);
    Livewire::test(CashIndex::class)->assertDontSee('Capital');
});
