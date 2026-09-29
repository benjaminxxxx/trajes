<?php

use App\Livewire\Inventory\PhysicalItemTracker;
use App\Models\Color;
use App\Models\Rental;
use App\Models\RentalDetail;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\Size;
use App\Services\Inventory\ItemTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(createAdmin());
});

function rentUnit($unit, string $date, float $price, string $status = 'returned'): void
{
    $rental = Rental::create([
        'code' => 'A-'.Str::random(6), 'client_id' => createClient()->id,
        'pickup_date' => $date, 'expected_return_date' => $date, 'status' => $status,
    ]);

    RentalDetail::create(['rental_id' => $rental->id, 'physical_product_id' => $unit->id, 'price' => $price]);
}

test('tracker page renders and searches products and physical units', function () {
    $variant = createProductIn(null);
    $unit = createPhysicalUnit($variant);

    $this->get(route('inventory.physical-items.index'))->assertOk();

    Livewire::test(PhysicalItemTracker::class)
        ->set('search', $variant->product->code)
        ->assertSee($variant->product->name)
        ->set('search', $unit->code)
        ->assertSee($unit->code);
});

test('unit history summarizes purchase, rentals, sale and profit', function () {
    $variant = createProductIn(null);
    $unit = createPhysicalUnit($variant);
    $unit->forceFill(['purchase_cost' => 200, 'created_at' => now()->subDays(30)])->save();

    rentUnit($unit, now()->subDays(20)->toDateString(), 80);
    rentUnit($unit, now()->subDays(10)->toDateString(), 90);
    rentUnit($unit, now()->subDays(5)->toDateString(), 999, status: 'cancelled');

    $sale = Sale::create(['code' => 'V-1', 'sale_date' => now()->toDateString(), 'subtotal' => 250, 'total' => 295, 'status' => 'completed']);
    SaleDetail::create([
        'sale_id' => $sale->id, 'product_variant_id' => $variant->id, 'physical_product_id' => $unit->id,
        'quantity' => 1, 'unit_cost' => 200, 'unit_price' => 250, 'subtotal' => 250,
    ]);
    $unit->update(['status' => 'sold']);

    $history = app(ItemTrackingService::class)->unitHistory($unit->fresh());

    expect($history['rentals_count'])->toBe(2)
        ->and($history['rentals'])->toHaveCount(3)
        ->and($history['rental_income'])->toEqual(170.0)
        ->and($history['sale']->sale_id)->toBe($sale->id)
        ->and($history['days_in_store'])->toBe(30)
        ->and($history['profit'])->toEqual(220.0); // 170 + 250 − 200

    Livewire::test(PhysicalItemTracker::class)
        ->call('selectUnit', $unit->id)
        ->assertSee($unit->code)
        ->assertSee('Vendida en V-1');
});

test('product stats are yearly and grouped by month, size and color', function () {
    $variant = createProductIn(null, stock: 10);
    $variant->product->update(['allow_rental' => false]);

    $m = Size::create(['name' => 'M', 'type' => 'estandar']);
    $l = Size::create(['name' => 'L', 'type' => 'estandar']);
    $black = Color::create(['name' => 'Negro']);
    $variant->update(['size_id' => $m->id, 'color_id' => $black->id]);
    $other = $variant->replicate()->fill(['size_id' => $l->id, 'sku' => null]);
    $other->save();

    sellVariant($variant, '2026-02-10', quantity: 3);
    sellVariant($other, '2026-02-20', quantity: 1);
    sellVariant($variant, '2026-07-01', quantity: 2);
    sellVariant($variant, '2025-07-01', quantity: 9);
    sellVariant($variant, '2026-08-01', quantity: 5, status: 'cancelled');

    $stats = app(ItemTrackingService::class)->productYearStats($variant->product, 2026);

    expect($stats['units_sold'])->toBe(6)
        ->and($stats['months']['sold'][2])->toBe(4)
        ->and($stats['months']['sold'][7])->toBe(2)
        ->and($stats['by_size']->first())->toMatchArray(['name' => 'M', 'sold' => 5])
        ->and($stats['by_size']->last())->toMatchArray(['name' => 'L', 'sold' => 1])
        ->and($stats['by_color']->first())->toMatchArray(['name' => 'Negro', 'sold' => 6]);

    expect(app(ItemTrackingService::class)->years($variant->product))->toContain(2026, 2025);

    Livewire::test(PhysicalItemTracker::class)
        ->call('selectProduct', $variant->product_id)
        ->set('year', 2025)
        ->assertSee($variant->product->name)
        ->assertSee('Tallas que más se mueven');
});

test('the product list links each product to its tracking page', function () {
    $variant = createProductIn(null);
    $product = $variant->product;
    $url = route('inventory.physical-items.index', ['producto' => $product->id]);

    $this->get(route('inventory.products.index'))
        ->assertOk()
        ->assertSee($url, false);

    Livewire::withQueryParams(['producto' => $product->id])
        ->test(PhysicalItemTracker::class)
        ->assertSet('productId', $product->id)
        ->assertSee($product->name)
        ->assertSee('Tallas que más se mueven');
});
