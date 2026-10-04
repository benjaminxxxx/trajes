<?php

use App\Livewire\Inventory\AssetIndex;
use App\Livewire\Sale\SaleDetailModal;
use App\Livewire\Sale\SaleForm;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\Sale;
use App\Services\Assets\AssetService;
use App\Services\Billing\BillingService;
use App\Services\Core\DashboardService;
use App\Services\Documents\ReceiptService;
use App\Services\Finance\CashService;
use App\Services\Sale\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(createAdmin());
});

function sellableAsset(array $attributes = []): Asset
{
    return app(AssetService::class)->create([
        'name' => 'Portaterno',
        'type' => 'empaque',
        'quantity' => 10,
        'unit_cost' => 5.9,
        'sale_price' => 11.8,
        ...$attributes,
    ]);
}

function saleHeader(): array
{
    return ['document_type' => 'nota_venta', 'sale_date' => now()->toDateString()];
}

test('a good with a sale price is sold on its own from the sale form', function () {
    $cover = sellableAsset();

    Livewire::test(SaleForm::class)
        ->assertSee('Bienes en venta')
        ->set('assetToAdd', (string) $cover->id)
        ->assertSet('assetToAdd', '')
        ->assertSet("assetLines.asset-{$cover->id}.unit_price", 11.8)
        ->set("assetLines.asset-{$cover->id}.quantity", 3)
        ->call('save')
        ->assertHasNoErrors();

    $sale = Sale::with('assetDeliveries')->sole();
    $delivery = $sale->assetDeliveries->sole();

    expect((float) $sale->total)->toBe(35.4)
        ->and((float) $sale->subtotal)->toBe(30.0)
        ->and($sale->details()->count())->toBe(0)
        ->and($delivery->quantity)->toBe(3)
        ->and((float) $delivery->line_total)->toBe(35.4)
        ->and($cover->fresh()->quantity)->toBe(7)
        ->and(AssetMovement::where('type', 'sale')->sole()->quantity)->toBe(-3)
        ->and(app(CashService::class)->balance())->toBe(35.4);
});

test('the profit of a sold good is what was charged without IGV minus its cost', function () {
    $cover = sellableAsset();

    app(SaleService::class)->register(saleHeader(), [], [
        ['asset_id' => $cover->id, 'quantity' => 2, 'unit_price' => 11.8],
    ]);

    // Cobrado 23.60 (20.00 sin IGV) − costo 2 × 5.90.
    $stats = app(DashboardService::class)->periodStats(now()->startOfDay(), now()->endOfDay());

    expect($stats['sales_margin'])->toEqualWithDelta(8.2, 0.001)
        ->and($stats['sales_total'])->toEqual(23.6);

    Livewire::test(SaleDetailModal::class)
        ->call('loadSale', Sale::sole()->id)
        ->assertSee('Portaterno')
        ->assertSeeInOrder(['Bien del negocio', 'vendido', 'margen', '8.20']);
});

test('a good given for free does not change the total and still costs', function () {
    $cover = sellableAsset();
    $variant = createProductIn(null, stock: 2);
    $variant->product->update(['allow_rental' => false]);

    $sale = app(SaleService::class)->register(
        saleHeader(),
        [['product_id' => $variant->product_id, 'size_id' => null, 'color_id' => null, 'quantity' => 1, 'unit_price' => 118, 'discount_percent' => 0]],
        [['asset_id' => $cover->id, 'quantity' => 1, 'unit_price' => 0]],
    );

    expect((float) $sale->total)->toBe(118.0)
        ->and((float) $sale->assetDeliveries()->sole()->line_total)->toBe(0.0)
        ->and($cover->fresh()->quantity)->toBe(9)
        ->and(AssetMovement::where('type', 'delivery')->count())->toBe(1);

    // El regalo no se factura, y el comprobante interno se sigue generando con él.
    expect(app(BillingService::class)->lines($sale))->toHaveCount(1)
        ->and(app(ReceiptService::class)->render($sale))->toStartWith('%PDF');
});

test('the electronic invoice lists the sold goods and adds up to the total', function () {
    $cover = sellableAsset();
    $variant = createProductIn(null, stock: 2);
    $variant->product->update(['allow_rental' => false]);

    $sale = app(SaleService::class)->register(
        saleHeader(),
        [['product_id' => $variant->product_id, 'size_id' => null, 'color_id' => null, 'quantity' => 1, 'unit_price' => 149.9, 'line_total' => 149.9, 'discount_percent' => 0]],
        [['asset_id' => $cover->id, 'quantity' => 3, 'unit_price' => 11.8]],
    );

    $lines = app(BillingService::class)->lines($sale);

    expect($lines)->toHaveCount(2)
        ->and($lines[1])->toMatchArray(['descripcion' => 'Portaterno', 'cantidad' => 3, 'importe' => '35.40'])
        ->and(array_sum(array_column($lines, 'importe')))->toEqualWithDelta((float) $sale->total, 0.001);
});

test('cancelling the sale returns the goods to stock and the money leaves the cash', function () {
    $cover = sellableAsset();

    $sale = app(SaleService::class)->register(saleHeader(), [], [
        ['asset_id' => $cover->id, 'quantity' => 4, 'unit_price' => 11.8],
    ]);

    app(SaleService::class)->cancel($sale, 'Error');

    expect($cover->fresh()->quantity)->toBe(10)
        ->and(app(CashService::class)->balance())->toBe(0.0);
});

test('goods cannot be sold without a sale price or beyond their stock', function (array $attributes, int $quantity) {
    $asset = sellableAsset($attributes);

    expect(fn () => app(SaleService::class)->register(saleHeader(), [], [
        ['asset_id' => $asset->id, 'quantity' => $quantity, 'unit_price' => 10],
    ]))->toThrow(RuntimeException::class);

    expect(Sale::count())->toBe(0)
        ->and($asset->fresh()->quantity)->toBe(10);
})->with([
    'no sale price' => [['sale_price' => null], 1],
    'not enough stock' => [[], 11],
]);

test('goods without a sale price are not offered in the sale form', function () {
    sellableAsset(['name' => 'Mostrador', 'sale_price' => null]);
    sellableAsset(['name' => 'Gancho de madera', 'quantity' => 0]);

    Livewire::test(SaleForm::class)
        ->assertDontSee('Bienes en venta')
        ->call('save')
        ->assertHasErrors('items');
});

test('the sale price of a good is set and cleared from the goods screen', function () {
    Livewire::test(AssetIndex::class)
        ->call('create')
        ->set('name', 'Portaterno')
        ->set('type', 'empaque')
        ->set('quantity', 20)
        ->set('unit_cost', 6)
        ->set('sale_price', 12)
        ->set('paidFromCash', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Se vende a S/ 12.00');

    $asset = Asset::sole();

    expect((float) $asset->sale_price)->toBe(12.0)
        ->and(app(CashService::class)->balance())->toBe(-120.0);

    Livewire::test(AssetIndex::class)
        ->call('edit', $asset->id)
        ->assertSet('sale_price', 12.0)
        ->set('sale_price', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($asset->fresh()->sale_price)->toBeNull();
});
