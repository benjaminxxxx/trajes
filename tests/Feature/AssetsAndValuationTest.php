<?php

use App\Livewire\Core\SystemSettings;
use App\Livewire\Inventory\AssetIndex;
use App\Livewire\Inventory\CompanyValuation;
use App\Livewire\Sale\SaleForm;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\DepreciationRule;
use App\Models\Rental;
use App\Models\RentalDetail;
use App\Models\Sale;
use App\Services\Assets\AssetService;
use App\Services\Core\DashboardService;
use App\Services\Inventory\ValuationService;
use App\Services\Sale\SaleService;
use Database\Seeders\AssetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createAsset(array $attributes = [], array $categoryIds = []): Asset
{
    return app(AssetService::class)->create([
        'name' => 'Ganchos de madera',
        'type' => 'exhibicion',
        'quantity' => 20,
        'unit_cost' => 3,
        ...$attributes,
    ], $categoryIds);
}

function rentUnitTimes($unit, int $times): void
{
    foreach (range(1, $times) as $index) {
        $rental = Rental::create([
            'code' => 'A-'.Str::random(6),
            'client_id' => createClient()->id,
            'pickup_date' => now(),
            'expected_return_date' => now(),
            'status' => Rental::STATUS_RETURNED,
        ]);

        RentalDetail::create(['rental_id' => $rental->id, 'physical_product_id' => $unit->id, 'price' => 50]);
    }
}

// ---------------- Bienes ----------------

test('only admins manage assets, valuation and settings', function () {
    $this->actingAs(createSeller());

    $this->get(route('inventory.assets.index'))->assertForbidden();
    $this->get(route('inventory.valuation.index'))->assertForbidden();
    $this->get(route('core.settings.index'))->assertForbidden();

    $this->actingAs(createAdmin());

    $this->get(route('inventory.assets.index'))->assertOk();
    $this->get(route('inventory.valuation.index'))->assertOk();
    $this->get(route('core.settings.index'))->assertOk();
});

test('an asset is registered from the form with its value defaulting to the cost', function () {
    $this->actingAs(createAdmin());

    Livewire::test(AssetIndex::class)
        ->call('create')
        ->set('name', 'Escritorio')
        ->set('type', 'mobiliario')
        ->set('quantity', 1)
        ->set('unit_cost', 450)
        ->call('save')
        ->assertHasNoErrors();

    $asset = Asset::where('name', 'Escritorio')->firstOrFail();

    expect((float) $asset->current_unit_value)->toBe(450.0)
        ->and($asset->movements()->first()->type)->toBe('initial');
});

test('buying more units adds stock and averages cost and value', function () {
    $this->actingAs(createAdmin());
    $asset = createAsset(['quantity' => 20, 'unit_cost' => 3, 'current_unit_value' => 2]);

    Livewire::test(AssetIndex::class)
        ->call('openStock', $asset->id)
        ->set('movementQuantity', 10)
        ->set('movementCost', 6)
        ->call('addStock')
        ->assertHasNoErrors();

    $asset->refresh();

    expect($asset->quantity)->toBe(30)
        ->and(round((float) $asset->unit_cost, 2))->toBe(4.0)
        ->and(round((float) $asset->current_unit_value, 2))->toBe(3.33);
});

test('editing the current value is logged as a revaluation and keeps quantity and cost', function () {
    $this->actingAs(createAdmin());
    $asset = createAsset(['name' => 'Escritorio', 'quantity' => 1, 'unit_cost' => 450]);

    Livewire::test(AssetIndex::class)
        ->call('edit', $asset->id)
        ->set('current_unit_value', 300)
        ->set('quantity', 99)
        ->call('save')
        ->assertHasNoErrors();

    $asset->refresh();

    expect((float) $asset->current_unit_value)->toBe(300.0)
        ->and($asset->quantity)->toBe(1)
        ->and((float) $asset->unit_cost)->toBe(450.0)
        ->and($asset->movements()->where('type', 'revalue')->exists())->toBeTrue();
});

test('units are discharged with a reason and the asset is discharged when none remain', function () {
    $this->actingAs(createAdmin());
    $asset = createAsset(['name' => 'Maniquí', 'quantity' => 2, 'unit_cost' => 280]);

    Livewire::test(AssetIndex::class)
        ->call('openDischarge', $asset->id)
        ->set('movementQuantity', 3)
        ->set('movementNote', 'Malogrado')
        ->call('discharge')
        ->assertHasErrors('movementQuantity')
        ->set('movementQuantity', 2)
        ->call('discharge')
        ->assertHasNoErrors();

    $asset->refresh();

    expect($asset->quantity)->toBe(0)
        ->and($asset->status)->toBe(Asset::STATUS_DISCHARGED)
        ->and($asset->movements()->first()->note)->toBe('Malogrado');
});

test('deleting an asset is soft and audited with who did it', function () {
    $admin = createAdmin();
    $this->actingAs($admin);
    $asset = createAsset();

    Livewire::test(AssetIndex::class)->call('delete', $asset->id);

    expect(Asset::find($asset->id))->toBeNull()
        ->and(Asset::withTrashed()->find($asset->id))->not->toBeNull()
        ->and(AuditLog::where('auditable_type', $asset->getMorphClass())->where('auditable_id', $asset->id)->value('user_id'))->toBe($admin->id);
});

test('the asset seeder creates goods, deliverable consumables and wear rules without duplicating', function () {
    $suits = Category::create(['name' => 'Ternos y Esmóquines', 'slug' => 'ternos-y-esmoquines']);

    $this->seed(AssetSeeder::class);
    $this->seed(AssetSeeder::class);

    $cover = Asset::where('name', 'Portaterno')->firstOrFail();

    expect(Asset::where('name', 'Ganchos de madera para terno')->count())->toBe(1)
        ->and(Asset::where('name', 'Escritorio de atención')->value('unit_cost'))->toEqual(450)
        ->and($cover->delivers_with_sale)->toBeTrue()
        ->and($cover->categories->pluck('id')->all())->toBe([$suits->id])
        ->and(DepreciationRule::count())->toBe(3);
});

// ---------------- Consumibles entregados con la venta ----------------

test('the sale form asks for consumables that apply to the cart categories', function () {
    $this->actingAs(createAdmin());
    $suits = Category::create(['name' => 'Ternos', 'slug' => 'ternos']);
    $dresses = Category::create(['name' => 'Vestidos', 'slug' => 'vestidos']);
    createAsset(['name' => 'Portaterno', 'type' => 'empaque', 'is_consumable' => true, 'delivers_with_sale' => true], [$suits->id]);
    createAsset(['name' => 'Correa', 'type' => 'accesorio', 'is_consumable' => true, 'delivers_with_sale' => true], [$dresses->id]);
    $variant = createProductIn($suits, stock: 2);

    Livewire::test(SaleForm::class)
        ->set('items', ['line' => [
            'product_id' => $variant->product_id, 'product_name' => 'Terno', 'product_code' => 'T-1', 'category_id' => $suits->id,
            'size_id' => null, 'color_id' => null, 'physical_product_id' => null,
            'quantity' => 2, 'unit_price' => 100, 'line_total' => 200, 'discount_percent' => 0,
        ]])
        ->assertSee('¿Se entrega con Portaterno?')
        ->assertDontSee('¿Se entrega con Correa?');
});

test('a sale delivers consumables, their cost lowers the margin and cancelling returns them', function () {
    $this->actingAs(createAdmin());
    $suits = Category::create(['name' => 'Ternos', 'slug' => 'ternos']);
    $cover = createAsset(['name' => 'Portaterno', 'type' => 'empaque', 'quantity' => 10, 'unit_cost' => 6, 'is_consumable' => true, 'delivers_with_sale' => true], [$suits->id]);
    $variant = createProductIn($suits, stock: 2);
    $variant->product->update(['allow_rental' => false]);

    Livewire::test(SaleForm::class)
        ->set('items', ['line' => [
            'product_id' => $variant->product_id, 'product_name' => 'Terno', 'product_code' => 'T-1', 'category_id' => $suits->id,
            'size_id' => null, 'color_id' => null, 'physical_product_id' => null,
            'quantity' => 2, 'unit_price' => 118, 'line_total' => 236, 'discount_percent' => 0,
        ]])
        ->set("deliveries.{$cover->id}.enabled", true)
        ->call('save');

    $sale = Sale::firstOrFail();

    expect($cover->fresh()->quantity)->toBe(8)
        ->and($sale->assetDeliveries()->sum('quantity'))->toBe(2);

    // Venta neta 200, costo de prendas 0, portaternos 2 × 6.
    $stats = app(DashboardService::class)->periodStats(now()->startOfDay(), now()->endOfDay());
    expect($stats['sales_margin'])->toBe(188.0);

    app(SaleService::class)->cancel($sale, 'Error de registro');

    expect($cover->fresh()->quantity)->toBe(10);
});

test('a sale cannot deliver more consumables than in stock', function () {
    $this->actingAs(createAdmin());
    $cover = createAsset(['name' => 'Portaterno', 'quantity' => 1, 'is_consumable' => true, 'delivers_with_sale' => true]);
    $variant = createProductIn(null, stock: 1);
    $variant->product->update(['allow_rental' => false]);

    expect(fn () => app(SaleService::class)->register(
        ['document_type' => 'nota_venta', 'sale_date' => now()->toDateString()],
        [['product_id' => $variant->product_id, 'quantity' => 1, 'unit_price' => 100]],
        [$cover->id => 2],
    ))->toThrow(RuntimeException::class);

    expect(Sale::count())->toBe(0)
        ->and($cover->fresh()->quantity)->toBe(1);
});

// ---------------- Desgaste y valorizado ----------------

test('garments lose value according to the highest rule reached', function (int $rentals, float $expected) {
    DepreciationRule::create(['min_rentals' => 3, 'percent' => 10]);
    DepreciationRule::create(['min_rentals' => 5, 'percent' => 25]);
    DepreciationRule::create(['min_rentals' => 10, 'percent' => 40]);

    expect(app(ValuationService::class)->depreciationPercent($rentals))->toBe($expected);
})->with([
    [2, 0.0],
    [3, 10.0],
    [7, 25.0],
    [12, 40.0],
]);

test('the company valuation adds garments with wear, sale stock and assets', function () {
    $this->actingAs(createAdmin());
    DepreciationRule::create(['min_rentals' => 3, 'percent' => 10]);

    $suit = createProductIn(null);
    $wornUnit = createPhysicalUnit($suit);
    $wornUnit->update(['purchase_cost' => 200]);
    rentUnitTimes($wornUnit, 3);
    createPhysicalUnit($suit)->update(['purchase_cost' => 100]);
    createPhysicalUnit($suit, 'sold')->update(['purchase_cost' => 500]);

    $shirt = createProductIn(null, stock: 4);
    $shirt->product->update(['allow_rental' => false]);
    $shirt->update(['average_cost' => 25]);

    createAsset(['name' => 'Escritorio', 'quantity' => 1, 'unit_cost' => 450, 'current_unit_value' => 300]);

    $summary = app(ValuationService::class)->summary();

    expect($summary['garments'])->toMatchArray(['count' => 2, 'cost' => 300.0, 'value' => 280.0])
        ->and($summary['stock'])->toMatchArray(['count' => 4, 'cost' => 100.0, 'value' => 100.0])
        ->and($summary['assets'])->toMatchArray(['count' => 1, 'cost' => 450.0, 'value' => 300.0])
        ->and($summary['cost'])->toBe(850.0)
        ->and($summary['value'])->toBe(680.0);

    Livewire::test(CompanyValuation::class)
        ->assertSee('680.00')
        ->assertSee($wornUnit->code)
        ->assertSee('−10 %');
});

test('depreciation rules are edited from the settings', function () {
    $this->actingAs(createAdmin());
    DepreciationRule::create(['min_rentals' => 3, 'percent' => 10]);

    Livewire::test(SystemSettings::class)
        ->call('addRule')
        ->set('rules', [
            'a' => ['min_rentals' => 3, 'percent' => 15],
            'b' => ['min_rentals' => 3, 'percent' => 30],
        ])
        ->call('save')
        ->assertHasErrors('rules.a.min_rentals')
        ->set('rules.b.min_rentals', 6)
        ->call('save')
        ->assertHasNoErrors();

    expect(DepreciationRule::orderBy('min_rentals')->pluck('percent', 'min_rentals')->map(fn ($percent) => (float) $percent)->all())
        ->toBe([3 => 15.0, 6 => 30.0]);
});
