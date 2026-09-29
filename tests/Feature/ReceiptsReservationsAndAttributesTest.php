<?php

use App\Livewire\Inventory\AttributeCatalog;
use App\Livewire\Inventory\CategoryFormModal;
use App\Livewire\Rental\RentalForm;
use App\Livewire\Rental\ReservationsModal;
use App\Livewire\Sale\SaleForm;
use App\Livewire\Shared\ProductPicker;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Size;
use App\Models\User;
use App\Services\Documents\ReceiptService;
use App\Services\Rental\RentalService;
use App\Services\Rental\ReservationService;
use App\Services\Sale\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

/**
 * Venta de una prenda física (precio con IGV 118) para un cliente con celular.
 */
function sellOneUnit(?string $mobile = '987654321'): Sale
{
    test()->actingAs(createAdmin());
    $variant = createProductIn(null, stock: 1);
    $unit = createPhysicalUnit($variant);
    $client = createClient();
    $client->update(['mobile' => $mobile]);

    return app(SaleService::class)->register(
        ['client_id' => $client->id, 'document_type' => 'nota_venta', 'sale_date' => now()->toDateString()],
        [['product_id' => $variant->product_id, 'physical_product_id' => $unit->id, 'quantity' => 1, 'unit_price' => 118]],
    );
}

// ---------------- Comprobantes PDF ----------------

test('the receipt viewer shows the pdf and a whatsapp share link to the client', function () {
    $sale = sellOneUnit('987 654 321');

    $this->actingAs(createSeller())
        ->get(route('receipts.show', ['type' => 'venta', 'id' => $sale->id]))
        ->assertOk()
        ->assertSee('Comprobante de venta')
        ->assertSee(route('receipts.file', ['venta', $sale->id]), false)
        ->assertSee('phone=51987654321', false);
});

test('the pdf is generated, stored and served only with permission', function () {
    $sale = sellOneUnit();

    $response = $this->actingAs(createSeller())->get(route('receipts.file', ['venta', $sale->id]));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(substr($response->streamedContent(), 0, 4))->toBe('%PDF');
    Storage::disk('local')->assertExists("receipts/ventas/{$sale->code}.pdf");

    $this->actingAs(User::factory()->create())
        ->get(route('receipts.file', ['venta', $sale->id]))
        ->assertForbidden();
});

test('the public receipt link only works when signed', function () {
    $sale = sellOneUnit();
    $publicUrl = app(ReceiptService::class)->publicUrl($sale);

    auth()->logout();

    $this->get($publicUrl)->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->get(route('receipts.public', ['venta', $sale->id]))->assertForbidden();
});

test('peruvian mobile numbers are normalized for whatsapp', function (?string $mobile, ?string $expected) {
    expect(app(ReceiptService::class)->whatsappPhone($mobile))->toBe($expected);
})->with([
    ['987654321', '51987654321'],
    ['+51 987 654 321', '51987654321'],
    ['01 4567890', null],
    [null, null],
]);

test('registering a sale opens its receipt', function () {
    $this->actingAs(createAdmin());
    $variant = createProductIn(null, stock: 3);
    $variant->product->update(['allow_rental' => false]);

    $component = Livewire::test(SaleForm::class)
        ->set('items', ['line' => [
            'product_id' => $variant->product_id, 'product_name' => 'Camisa', 'product_code' => 'C-1', 'category_id' => null,
            'size_id' => null, 'color_id' => null, 'physical_product_id' => null,
            'quantity' => 1, 'unit_price' => 59, 'line_total' => 59, 'discount_percent' => 0,
        ]])
        ->call('save');

    $sale = Sale::firstOrFail();

    $component->assertRedirect(route('receipts.show', ['type' => 'venta', 'id' => $sale->id]));
});

// ---------------- Separación de prendas ----------------

test('garments are reserved from the rental form without blocking them', function () {
    $this->actingAs(createSeller());
    $variant = createProductIn(null);
    $unit = createPhysicalUnit($variant);
    $client = createClient();

    Livewire::test(RentalForm::class)
        ->set('client_id', $client->id)
        ->set('client_name', $client->display_name)
        ->set('pickup_date', '2026-10-15T10:00')
        ->set('notes', 'Boda del sábado')
        ->set('items', ['line' => [
            'product_id' => $variant->product_id, 'product_name' => 'Terno', 'product_code' => 'T-1', 'category_id' => null,
            'size_id' => null, 'color_id' => null, 'physical_product_id' => $unit->id, 'physical_code' => $unit->code,
            'quantity' => 1, 'unit_price' => 80, 'discount_percent' => 0,
        ]])
        ->call('reserve')
        ->assertHasNoErrors()
        ->assertSet('items', []);

    $unit->refresh();

    expect($unit->reserved_client_id)->toBe($client->id)
        ->and($unit->reserved_until->toDateString())->toBe('2026-10-15')
        ->and($unit->reservation_note)->toBe('Boda del sábado')
        ->and($unit->status)->toBe('available');

    // Sigue ofreciéndose en el selector, con el aviso de separación.
    Livewire::test(ProductPicker::class)
        ->call('open', 'rental-gallery', $variant->product_id, 'rental')
        ->assertSee('Separada: '.$client->display_name);
});

test('a reservation is cleared when sold, or rented by the same client, but kept for another client', function () {
    $this->actingAs(createAdmin());
    $variant = createProductIn(null, stock: 3);
    [$sold, $sameClient, $otherClient] = [createPhysicalUnit($variant), createPhysicalUnit($variant), createPhysicalUnit($variant)];
    $owner = createClient();
    $reservations = app(ReservationService::class);
    $reservations->reserve([$sold->id, $sameClient->id, $otherClient->id], $owner, now()->addWeek()->toDateString());

    app(SaleService::class)->register(
        ['document_type' => 'nota_venta', 'sale_date' => now()->toDateString()],
        [['product_id' => $variant->product_id, 'physical_product_id' => $sold->id, 'quantity' => 1, 'unit_price' => 100]],
    );

    foreach ([[$sameClient, $owner->id], [$otherClient, createClient()->id]] as [$unit, $clientId]) {
        app(RentalService::class)->register(
            ['client_id' => $clientId, 'pickup_date' => now(), 'expected_return_date' => now()->addDay()],
            [['product_id' => $variant->product_id, 'physical_product_id' => $unit->id, 'quantity' => 1, 'unit_price' => 80]],
        );
    }

    expect($sold->fresh()->isReserved())->toBeFalse()
        ->and($sameClient->fresh()->isReserved())->toBeFalse()
        ->and($otherClient->fresh()->reserved_client_id)->toBe($owner->id);
});

test('reservations are listed and can be released', function () {
    $this->actingAs(createSeller());
    $unit = createPhysicalUnit(createProductIn(null));
    app(ReservationService::class)->reserve([$unit->id], createClient(), now()->subDay()->toDateString());

    Livewire::test(ReservationsModal::class)
        ->call('open')
        ->assertSee($unit->code)
        ->assertSee('Vencida')
        ->call('release', $unit->id)
        ->assertDispatched('reservationsChanged');

    expect($unit->fresh()->isReserved())->toBeFalse();
});

// ---------------- Tallas, colores y marcas ----------------

test('only admins manage sizes, colors and brands', function () {
    $this->actingAs(createSeller())->get(route('inventory.attributes.index'))->assertForbidden();
    $this->actingAs(createAdmin())->get(route('inventory.attributes.index'))->assertOk();
});

test('an unused size can be renamed and deleted', function () {
    $this->actingAs(createAdmin());
    $size = Size::create(['name' => 'XXL', 'type' => 'estandar']);

    Livewire::test(AttributeCatalog::class)
        ->call('edit', $size->id)
        ->assertSet('nameLocked', false)
        ->set('name', '3XL')
        ->call('save')
        ->assertHasNoErrors();

    expect($size->fresh()->name)->toBe('3XL');

    Livewire::test(AttributeCatalog::class)->call('delete', $size->id);

    expect(Size::find($size->id))->toBeNull();
});

test('a size in use keeps its name, cannot be deleted and can be deactivated', function () {
    $this->actingAs(createAdmin());
    $size = Size::create(['name' => 'M', 'type' => 'estandar']);
    $variant = createProductIn(null);
    $variant->update(['size_id' => $size->id]);

    Livewire::test(AttributeCatalog::class)
        ->call('edit', $size->id)
        ->assertSet('nameLocked', true)
        ->set('name', 'XL')
        ->set('sort_order', 5)
        ->call('save')
        ->assertHasNoErrors();

    expect($size->fresh()->name)->toBe('M')
        ->and($size->fresh()->sort_order)->toBe(5);

    Livewire::test(AttributeCatalog::class)->call('delete', $size->id);
    expect(Size::find($size->id))->not->toBeNull();

    Livewire::test(AttributeCatalog::class)->call('toggleActive', $size->id);
    expect($size->fresh()->is_active)->toBeFalse();
});

test('inactive sizes are no longer offered but categories keep the ones they had', function () {
    $this->actingAs(createAdmin());
    $old = Size::create(['name' => '34', 'type' => 'terno', 'is_active' => false]);
    $retired = Size::create(['name' => '52', 'type' => 'terno', 'is_active' => false]);
    $current = Size::create(['name' => '40', 'type' => 'terno']);
    $category = Category::create(['name' => 'Ternos', 'slug' => 'ternos']);
    $category->sizes()->sync([$old->id]);

    $sizes = Livewire::test(CategoryFormModal::class)
        ->call('loadCategory', $category->id)
        ->viewData('sizesByType')
        ->flatten()
        ->pluck('name')
        ->all();

    expect($sizes)->toContain('40', '34')->not->toContain('52');
});

test('colors and brands validate duplicates and hex codes', function () {
    $this->actingAs(createAdmin());
    Color::create(['name' => 'Negro', 'hex_code' => '#000000']);
    Brand::create(['name' => 'Zara', 'slug' => 'zara']);

    Livewire::test(AttributeCatalog::class, ['tab' => 'colores'])
        ->call('create')
        ->set('name', 'Negro')
        ->set('hex_code', 'rojo')
        ->call('save')
        ->assertHasErrors(['name', 'hex_code']);

    Livewire::test(AttributeCatalog::class)
        ->set('tab', 'marcas')
        ->call('create')
        ->set('name', 'ZARA')
        ->call('save')
        ->assertHasErrors('name');

    Livewire::test(AttributeCatalog::class)
        ->set('tab', 'marcas')
        ->call('create')
        ->set('name', 'Hugo Boss')
        ->call('save')
        ->assertHasNoErrors();

    expect(Brand::where('slug', 'hugo-boss')->exists())->toBeTrue()
        ->and(Product::count())->toBe(0);
});
