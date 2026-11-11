<?php

use App\Livewire\Inventory\ProductCatalog;
use App\Livewire\Shared\ProductDetailModal;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\Supplier;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->actingAs(createAdmin());
    $this->dresses = Category::create(['name' => 'Vestidos', 'slug' => 'vestidos']);
});

function catalogItem(Category $category, string $name, string $gender, array $attributes = []): Product
{
    return Product::create([
        'category_id' => $category->id, 'code' => strtoupper(Str::random(6)), 'name' => $name,
        'slug' => Str::slug($name).'-'.Str::random(4), 'price' => 100, 'gender' => $gender,
        ...$attributes,
    ]);
}

test('the catalog filters by type and search and hides inactive products', function () {
    catalogItem($this->dresses, 'Vestido Rocío', 'femenino');
    catalogItem($this->dresses, 'Terno Slim', 'masculino');
    catalogItem($this->dresses, 'Vestido Viejo', 'femenino', ['is_active' => false]);

    Livewire::test(ProductCatalog::class)
        ->assertSee('Vestido Rocío')
        ->assertSee('Terno Slim')
        ->assertDontSee('Vestido Viejo')
        ->call('selectGender', 'masculino')
        ->assertSee('Terno Slim')
        ->assertDontSee('Vestido Rocío')
        ->call('selectGender', 'masculino')
        ->set('search', 'rocío')
        ->assertSee('Vestido Rocío')
        ->assertDontSee('Terno Slim');
});

test('a rental-only product shows its rental price', function () {
    $product = catalogItem($this->dresses, 'Vestido de Gala', 'femenino', ['allow_sale' => false, 'allow_rental' => true]);
    ProductVariant::create(['product_id' => $product->id, 'rental_price' => 80]);

    Livewire::test(ProductCatalog::class)->assertSee('S/ 80.00')->assertSee('alquiler');
});

test('the catalog reel can be shared and shows cost and supplier only with permission', function () {
    $product = catalogItem($this->dresses, 'Vestido Rocío', 'femenino');
    $variant = ProductVariant::create(['product_id' => $product->id, 'sale_price' => 150]);
    $variant->forceFill(['stock' => 2, 'average_cost' => 90])->save();

    $supplier = Supplier::create([
        'code' => 'PROV-000001', 'document_type' => 'RUC', 'document_number' => '20123456789', 'display_name' => 'Natzu',
    ]);
    $purchase = Purchase::create(['supplier_id' => $supplier->id, 'document_number' => 'F003-48', 'issue_date' => '2026-10-22']);
    $purchase->details()->create(['product_variant_id' => $variant->id, 'quantity' => 2, 'unit_price' => 90, 'subtotal' => 180]);
    Purchase::create(['supplier_id' => $supplier->id, 'issue_date' => '2026-10-23', 'status' => Purchase::STATUS_DRAFT])
        ->details()->create(['product_variant_id' => $variant->id, 'quantity' => 5, 'unit_price' => 70, 'subtotal' => 350]);

    $this->actingAs(createSeller());

    Livewire::test(ProductDetailModal::class)
        ->call('open', $product->id, 'catalog')
        ->assertDispatched('product-reel-open', fn (string $event, array $params) => $params['product']['shareable'] === true
            && (float) $params['product']['price'] === 150.0
            && $params['product']['internal'] == [
                'cost' => 90.0,
                'margin' => 60.0,
                'marginPercent' => 40.0,
                'purchases' => [['date' => '22/10/2026', 'supplier' => 'Natzu', 'document' => 'F003-48', 'quantity' => 2, 'unitCost' => 90.0]],
            ]);

    // Sin el permiso de costos se puede compartir, pero no llega el costo.
    $this->actingAs(createMember());

    Livewire::test(ProductDetailModal::class)
        ->call('open', $product->id, 'catalog')
        ->assertDispatched('product-reel-open', fn (string $event, array $params) => $params['product']['internal'] === null);

    // En venta y alquiler el reel sigue igual: sin compartir ni costos.
    $this->actingAs(createAdmin());

    Livewire::test(ProductDetailModal::class)
        ->call('open', $product->id, 'sale', 'sale-gallery')
        ->assertDispatched('product-reel-open', fn (string $event, array $params) => $params['product']['shareable'] === false
            && $params['product']['internal'] === null);
});

test('sellers can open the catalog and members without permission cannot', function () {
    $this->actingAs(createSeller())->get(route('inventory.catalog.index'))->assertOk();
    $this->actingAs(createMember())->get(route('inventory.catalog.index'))->assertForbidden();
});
