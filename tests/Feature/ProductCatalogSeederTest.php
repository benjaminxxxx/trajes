<?php

use App\Models\PhysicalProduct;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\Purchase;
use Database\Seeders\CategoryAndAttributeSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    $this->seed(CategoryAndAttributeSeeder::class);
});

test('catalog seeder loads products with stock, physical units and reduced images', function () {
    $this->seed(ProductCatalogSeeder::class);

    $terno = Product::where('code', 'TRN-BAR-001')->firstOrFail();
    $zapato = Product::where('code', 'ZAP-V-001')->firstOrFail();

    expect(Product::count())->toBe(21)
        ->and(Product::where('name', 'Vestido Fiorela')->exists())->toBeTrue()
        ->and(Product::whereHas('category', fn ($q) => $q->where('name', 'Pantalones Palazo'))->count())->toBe(2)
        ->and(Purchase::count())->toBe(1);

    // Terno alquilable: 2 colores × 3 tallas × 2 unidades = 12 prendas físicas con su costo.
    expect($terno->allow_rental)->toBeTrue()
        ->and($terno->physicalProducts()->count())->toBe(12)
        ->and((float) $terno->physicalProducts()->first()->purchase_cost)->toEqual(280.0)
        ->and((float) $terno->variants()->first()->average_cost)->toEqual(280.0);

    // Zapato solo venta: stock en la variante, sin prendas físicas.
    expect($zapato->allow_rental)->toBeFalse()
        ->and((int) $zapato->variants()->sum('stock'))->toBe(15)
        ->and(PhysicalProduct::whereIn('product_variant_id', $zapato->variants()->pluck('id'))->count())->toBe(0);

    $media = ProductMedia::where('product_id', $terno->id)->firstOrFail();
    Storage::disk('public')->assertExists($media->file_path);

    [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($media->file_path));

    expect($media->is_primary)->toBeTrue()
        ->and($width)->toBeLessThanOrEqual(800)
        ->and($height)->toBeLessThanOrEqual(1000);
});

test('catalog seeder does not duplicate products when run twice', function () {
    $this->seed(ProductCatalogSeeder::class);
    $this->seed(ProductCatalogSeeder::class);

    expect(Product::count())->toBe(21)
        ->and(Purchase::count())->toBe(1);
});
