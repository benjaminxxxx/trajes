<?php

use App\Livewire\Inventory\ProductFormModal;
use App\Livewire\Purchasing\PurchaseForm;
use App\Livewire\Purchasing\SupplierModal;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\Size;
use App\Services\Inventory\ProductImageOptimizer;
use App\Services\Inventory\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(createAdmin());
});

// ---------------- Modal de producto ----------------

test('a product is created with a brand and category that did not exist yet', function () {
    $size = Size::create(['name' => 'M', 'type' => 'estandar']);
    $inactiveSize = Size::create(['name' => 'XXL', 'type' => 'estandar', 'is_active' => false]);
    $color = Color::create(['name' => 'Negro', 'hex_code' => '#000000']);

    Livewire::test(ProductFormModal::class)
        ->call('open')
        ->set('name', 'Terno Slim Fit')
        ->set('category', 'Ternos')
        ->set('brand', 'Gamarra Kings')
        ->set('price', 250)
        ->set('purchase_price', 140)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('show', false)
        ->assertDispatched('product-saved', created: true);

    $product = Product::with('category.sizes', 'category.colors', 'brand')->where('name', 'Terno Slim Fit')->sole();

    expect($product->brand->name)->toBe('Gamarra Kings')
        ->and($product->category->name)->toBe('Ternos')
        ->and($product->code)->toStartWith('PROD-')
        ->and($product->allow_rental)->toBeTrue()
        ->and($product->allow_sale)->toBeTrue()
        ->and((float) $product->price)->toEqual(250.0)
        ->and((float) $product->purchase_price)->toEqual(140.0)
        // La categoría nueva nace con las tallas y colores activos para poder comprar de inmediato.
        ->and($product->category->sizes->pluck('id')->all())->toBe([$size->id])
        ->and($product->category->sizes->pluck('id'))->not->toContain($inactiveSize->id)
        ->and($product->category->colors->pluck('id')->all())->toBe([$color->id]);
});

test('typing an existing brand or category reuses it instead of duplicating it', function () {
    $category = Category::create(['name' => 'Camisas', 'slug' => 'camisas']);
    $brand = Brand::create(['name' => 'Van Heusen', 'slug' => 'van-heusen']);

    Livewire::test(ProductFormModal::class)
        ->call('open')
        ->set('name', 'Camisa blanca')
        ->set('category', ' camisas ')
        ->set('brand', 'VAN HEUSEN')
        ->call('save')
        ->assertHasNoErrors();

    $product = Product::where('name', 'Camisa blanca')->sole();

    expect($product->category_id)->toBe($category->id)
        ->and($product->brand_id)->toBe($brand->id)
        ->and(Category::count())->toBe(1)
        ->and(Brand::count())->toBe(1);
});

test('the single usage control decides whether the product is rented, sold or both', function (string $usage, bool $rental, bool $sale) {
    Livewire::test(ProductFormModal::class)
        ->call('open')
        ->set('name', 'Corbata')
        ->set('category', 'Accesorios')
        ->set('usage', $usage)
        ->call('save')
        ->assertHasNoErrors();

    $product = Product::where('name', 'Corbata')->sole();

    expect($product->allow_rental)->toBe($rental)
        ->and($product->allow_sale)->toBe($sale);
})->with([
    'both' => ['both', true, true],
    'rental only' => ['rental', true, false],
    'sale only' => ['sale', false, true],
]);

test('the first photos are saved with the new product, reduced without stretching', function () {
    Storage::fake('public');

    Livewire::test(ProductFormModal::class)
        ->call('open')
        ->set('name', 'Vestido de gala')
        ->set('category', 'Vestidos')
        ->set('photoUploads', [UploadedFile::fake()->image('celular.jpg', 3000, 2000)])
        ->assertCount('photos', 1)
        ->assertSet('photoUploads', [])
        ->set('photoUploads', [UploadedFile::fake()->image('otra.png', 400, 300)])
        ->assertCount('photos', 2)
        ->call('save')
        ->assertHasNoErrors();

    $media = Product::where('name', 'Vestido de gala')->sole()->media;

    expect($media)->toHaveCount(2)
        ->and($media[0]->is_primary)->toBeTrue()
        ->and($media[1]->is_primary)->toBeFalse()
        ->and($media[0]->file_path)->toEndWith('.jpg');

    [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($media[0]->file_path));
    [$smallWidth, $smallHeight] = getimagesizefromstring(Storage::disk('public')->get($media[1]->file_path));

    // 3000×2000 baja a 800 de ancho conservando la proporción; la pequeña no se agranda.
    expect([$width, $height])->toBe([ProductImageOptimizer::MAX_WIDTH, 533])
        ->and([$smallWidth, $smallHeight])->toBe([400, 300]);
});

test('a file that is not a photo is rejected before it reaches the product', function () {
    Livewire::test(ProductFormModal::class)
        ->call('open')
        ->set('photoUploads', [UploadedFile::fake()->create('factura.pdf', 100, 'application/pdf')])
        ->assertHasErrors('photoUploads.0')
        ->assertCount('photos', 0);
});

test('a duplicated product name or code is rejected', function () {
    $category = Category::create(['name' => 'Ternos', 'slug' => 'ternos']);
    Product::create(['category_id' => $category->id, 'code' => 'T-1', 'name' => 'Terno clásico', 'slug' => 't-1']);

    Livewire::test(ProductFormModal::class)
        ->call('open')
        ->set('name', 'Terno clásico')
        ->set('category', 'Ternos')
        ->call('save')
        ->assertHasErrors('name');

    Livewire::test(ProductFormModal::class)
        ->call('open')
        ->set('name', 'Terno clásico')
        ->set('category', 'Categoría fantasma')
        ->set('moreOptions', true)
        ->set('code', 'T-1')
        ->call('save')
        ->assertHasErrors('code');

    expect(Product::count())->toBe(1)
        ->and(Category::count())->toBe(1);
});

test('a category in the trash is not silently recreated', function () {
    Category::create(['name' => 'Smokings', 'slug' => 'smokings'])->delete();

    Livewire::test(ProductFormModal::class)
        ->call('open')
        ->set('name', 'Smoking negro')
        ->set('category', 'Smokings')
        ->call('save')
        ->assertHasErrors('category');

    expect(Product::count())->toBe(0);
});

test('without permission to create them, unknown categories and brands are rejected', function () {
    $service = app(ProductService::class);

    expect(fn () => $service->resolverCategoria('Nueva', canCreate: false))->toThrow(ValidationException::class)
        ->and(fn () => $service->resolverMarca('Nueva', canCreate: false))->toThrow(ValidationException::class)
        ->and($service->resolverMarca('  ', canCreate: false))->toBeNull()
        ->and(Category::count())->toBe(0)
        ->and(Brand::count())->toBe(0);
});

test('a seller cannot save products from the modal', function () {
    $this->actingAs(createSeller());

    Livewire::test(ProductFormModal::class)
        ->call('open')
        ->set('name', 'Terno')
        ->set('category', 'Ternos')
        ->call('save')
        ->assertForbidden();

    expect(Product::count())->toBe(0);
});

test('editing a product with physical units keeps its classification', function () {
    $category = Category::create(['name' => 'Ternos', 'slug' => 'ternos']);
    $variant = createProductIn($category);
    createPhysicalUnit($variant);

    Livewire::test(ProductFormModal::class)
        ->call('open', $variant->product_id)
        ->assertSet('category', 'Ternos')
        ->assertSet('hasPhysicalItems', true)
        ->set('name', 'Terno renombrado')
        ->set('category', 'Otra categoría')
        ->set('usage', 'rental')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('product-saved', created: false);

    $product = $variant->product->fresh();

    expect($product->name)->toBe('Terno renombrado')
        ->and($product->category_id)->toBe($category->id)
        ->and($product->allow_sale)->toBeFalse()
        ->and(Category::count())->toBe(1);
});

// ---------------- Compra rápida ----------------

test('the purchase form and the catalog embed the product modal', function () {
    $this->get(route('purchasing.purchases.create'))->assertOk()->assertSeeLivewire(ProductFormModal::class);
    $this->get(route('inventory.products.index'))->assertOk()->assertSeeLivewire(ProductFormModal::class);
    $this->get('/inventario/productos/nuevo')->assertNotFound();
});

test('a product created from the purchase form goes straight into the purchase lines', function () {
    $category = Category::create(['name' => 'Ternos', 'slug' => 'ternos']);
    $product = Product::create(['category_id' => $category->id, 'code' => 'T-9', 'name' => 'Terno nuevo', 'slug' => 't-9', 'price' => 250, 'purchase_price' => 180]);

    $form = Livewire::test(PurchaseForm::class)
        ->call('addCreatedProduct', $product->id, 'purchase')
        ->assertCount('items', 1);

    expect(array_values($form->get('items'))[0])->toMatchArray([
        'product_id' => $product->id,
        'product_name' => 'Terno nuevo',
        'product_code' => 'T-9',
        'category_id' => $category->id,
        'unit_price' => 180.0,
        'quantity' => 1,
    ]);

    // Un producto guardado desde otra pantalla no se cuela en la compra.
    $form->call('addCreatedProduct', $product->id, '')->assertCount('items', 1);
});

// ---------------- Búsqueda de RUC del proveedor ----------------

test('looking up a RUC fills the supplier with the SUNAT data', function () {
    Http::fake(['*/ruc/20601112223*' => Http::response([
        'ruc' => '20601112223',
        'razonSocial' => 'TELAS DEL NORTE S.A.C.',
        'direccion' => 'JR. GAMARRA 123',
        'distrito' => 'LA VICTORIA',
        'provincia' => 'LIMA',
        'departamento' => 'LIMA',
        'telefonos' => ['014567890'],
        'estado' => 'ACTIVO',
    ])]);

    Livewire::test(SupplierModal::class)
        ->call('open')
        ->set('document_number', ' 20601112223 ')
        ->call('lookupRuc')
        ->assertHasNoErrors()
        ->assertSet('document_number', '20601112223')
        ->assertSet('company_name', 'TELAS DEL NORTE S.A.C.')
        ->assertSet('address', 'JR. GAMARRA 123, LA VICTORIA, LIMA, LIMA')
        ->assertSet('phone', '014567890');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'token='.config('services.sunat.token')));
});

test('a RUC 10 fills the names of a natural person', function () {
    Http::fake(['*' => Http::response(['ruc' => '10401258731', 'razonSocial' => 'MENDOZA QUISPE JULIO CESAR'])]);

    Livewire::test(SupplierModal::class)
        ->call('open')
        ->call('setType', 'individual')
        ->set('document_type', 'RUC')
        ->set('document_number', '10401258731')
        ->call('lookupRuc')
        ->assertSet('paternal_last_name', 'MENDOZA')
        ->assertSet('maternal_last_name', 'QUISPE')
        ->assertSet('names', 'JULIO CESAR');
});

test('a failed RUC lookup explains why and leaves the form to be filled by hand', function (Closure $fake, string $ruc) {
    $fake();

    Livewire::test(SupplierModal::class)
        ->call('open')
        ->set('document_number', $ruc)
        ->set('company_name', 'Escrito a mano')
        ->call('lookupRuc')
        ->assertHasErrors('document_number')
        ->assertSet('company_name', 'Escrito a mano');
})->with([
    'unknown RUC' => [fn () => Http::fake(['*' => Http::response(['message' => 'not found'], 404)]), '20999999999'],
    'empty answer' => [fn () => Http::fake(['*' => Http::response([])]), '20999999999'],
    'service down' => [fn () => Http::fake(['*' => Http::failedConnection()]), '20999999999'],
]);

test('an invalid RUC is rejected without calling the service', function () {
    Http::fake();

    Livewire::test(SupplierModal::class)
        ->call('open')
        ->set('document_number', '12345678')
        ->call('lookupRuc')
        ->assertHasErrors('document_number');

    Http::assertNothingSent();
});
