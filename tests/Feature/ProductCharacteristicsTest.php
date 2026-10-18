<?php

use App\Livewire\Inventory\CharacteristicCatalog;
use App\Livewire\Inventory\ProductFormModal;
use App\Livewire\Purchasing\PurchaseForm;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\Product;
use App\Services\Inventory\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(createAdmin());
    $this->suits = Category::create(['name' => 'Ternos', 'slug' => 'ternos']);
    $this->shoes = Category::create(['name' => 'Zapatos', 'slug' => 'zapatos']);
});

function characteristicFor(string $name, array $categories = [], array $values = []): Attribute
{
    $attribute = Attribute::create(['name' => $name]);
    $attribute->categories()->sync(collect($categories)->pluck('id'));

    foreach ($values as $value) {
        $attribute->values()->create(['value' => $value]);
    }

    return $attribute;
}

function productNamed(Category $category, string $name): Product
{
    return Product::create(['category_id' => $category->id, 'code' => strtoupper(Str::random(6)), 'name' => $name, 'slug' => Str::slug($name).'-'.Str::random(4), 'price' => 100]);
}

test('the form only asks for the characteristics of the chosen category', function () {
    characteristicFor('Tela', [$this->suits]);
    characteristicFor('Cuero', [$this->shoes]);
    characteristicFor('Procedencia');

    $form = Livewire::test(ProductFormModal::class)
        ->call('open')
        ->call('chooseCategory', $this->suits->id)
        ->assertSet('step', 'form')
        ->assertSet('category', 'Ternos');

    expect(collect($form->get('characteristics'))->pluck('name')->all())->toBe(['Procedencia', 'Tela']);

    $form->set('category', 'Zapatos');

    expect(collect($form->get('characteristics'))->pluck('name')->all())->toEqualCanonicalizing(['Cuero', 'Procedencia']);
});

test('characteristics are saved as text and new values become suggestions', function () {
    $fabric = characteristicFor('Tela', [$this->suits], ['Lana']);

    Livewire::test(ProductFormModal::class)
        ->call('open')
        ->call('chooseCategory', $this->suits->id)
        ->set('name', 'Terno Sesgo')
        ->set('price', 300)
        ->set('characteristics.0.value', ' Satín ')
        ->set('newCharacteristic', 'Corte')
        ->call('addCharacteristic')
        ->set('characteristics.1.value', 'Sesgo')
        ->call('save')
        ->assertHasNoErrors();

    $product = Product::with('characteristics')->where('name', 'Terno Sesgo')->sole();

    expect($product->characteristicsSummary())->toBe('Satín · Sesgo')
        ->and($fabric->values()->pluck('value')->all())->toBe(['Lana', 'Satín'])
        // La característica nueva queda en el catálogo, pedida en la misma categoría.
        ->and(Attribute::where('name', 'Corte')->sole()->categories->pluck('id')->all())->toBe([$this->suits->id]);
});

test('changing the catalog never rewrites what a product already has', function () {
    $fabric = characteristicFor('Tela', [$this->suits], ['Satín']);
    $product = productNamed($this->suits, 'Vestido Rocío');
    app(ProductService::class)->sincronizarCaracteristicas($product, [
        ['attribute_id' => $fabric->id, 'name' => 'Tela', 'value' => 'Satín'],
    ]);

    $fabric->update(['name' => 'Tipo de tela']);
    $fabric->values()->delete();
    $fabric->delete();

    expect($product->characteristics()->sole()->only(['name', 'value']))->toBe(['name' => 'Tela', 'value' => 'Satín']);
});

test('a mistake is fixed on the product itself without touching others', function () {
    $fabric = characteristicFor('Tela', [$this->suits]);
    $wrong = productNamed($this->suits, 'Terno A');
    $right = productNamed($this->suits, 'Terno B');
    $service = app(ProductService::class);
    $service->sincronizarCaracteristicas($wrong, [['attribute_id' => $fabric->id, 'name' => 'Tela', 'value' => 'Satin']]);
    $service->sincronizarCaracteristicas($right, [['attribute_id' => $fabric->id, 'name' => 'Tela', 'value' => 'Satin']]);

    Livewire::test(ProductFormModal::class)
        ->call('open', $wrong->id)
        ->assertSet('step', 'form')
        ->set('characteristics.0.value', 'Satín')
        ->call('save')
        ->assertHasNoErrors();

    expect($wrong->characteristics()->value('value'))->toBe('Satín')
        ->and($right->characteristics()->value('value'))->toBe('Satin');
});

test('a new product starts by showing what the category already has', function () {
    $existing = productNamed($this->suits, 'Terno Slim Azul');

    Livewire::test(ProductFormModal::class)
        ->call('open')
        ->assertSet('step', 'category')
        ->assertSee('Ternos')
        ->call('chooseCategory', $this->suits->id)
        ->assertSet('step', 'products')
        ->assertSee('Terno Slim Azul')
        ->call('startNew', $this->suits->id)
        ->assertSet('step', 'form')
        ->set('name', 'Terno Slim')
        ->assertSee('Ya están registrados');

    // Una categoría sin productos va directo al formulario.
    Livewire::test(ProductFormModal::class)
        ->call('open')
        ->call('chooseCategory', $this->shoes->id)
        ->assertSet('step', 'form')
        ->assertSet('category', 'Zapatos');

    expect($existing->exists)->toBeTrue();
});

test('the finder matches products by their characteristics', function () {
    $product = productNamed($this->suits, 'Vestido Estrella');
    $product->characteristics()->create(['name' => 'Tela', 'value' => 'Satín']);

    Livewire::test(ProductFormModal::class)
        ->call('open')
        ->set('finder', 'satín')
        ->assertSee('Vestido Estrella');
});

test('an existing product is bought again instead of duplicated', function () {
    $product = productNamed($this->suits, 'Terno Slim Azul');
    $product->update(['purchase_price' => 120]);

    // Desde compras: entra directo a la compra.
    Livewire::test(ProductFormModal::class)
        ->call('open', null, 'purchase')
        ->call('useExisting', $product->id)
        ->assertDispatched('product-saved', productId: $product->id, context: 'purchase', created: false)
        ->assertSet('show', false);

    // Desde el catálogo: abre una compra con el producto.
    Livewire::test(ProductFormModal::class)
        ->call('open')
        ->call('useExisting', $product->id)
        ->assertRedirect(route('purchasing.purchases.create', ['producto' => $product->id]));

    $form = Livewire::withQueryParams(['producto' => $product->id])->test(PurchaseForm::class);

    expect(array_values($form->get('items')))->toHaveCount(1)
        ->and(array_values($form->get('items'))[0]['unit_price'])->toEqual(120.0);
});

test('the characteristics catalog manages names, categories and suggestions', function () {
    $this->get(route('inventory.characteristics.index'))->assertOk();

    Livewire::test(CharacteristicCatalog::class)
        ->call('create')
        ->set('name', 'Cuero')
        ->set('category_ids', [$this->shoes->id])
        ->call('save')
        ->assertHasNoErrors();

    $leather = Attribute::where('name', 'Cuero')->sole();

    Livewire::test(CharacteristicCatalog::class)
        ->set("newValues.{$leather->id}", 'Charol')
        ->call('addValue', $leather->id)
        ->set("newValues.{$leather->id}", 'charol')
        ->call('addValue', $leather->id);

    expect($leather->categories->pluck('id')->all())->toBe([$this->shoes->id])
        ->and($leather->values()->pluck('value')->all())->toBe(['Charol']);

    $this->actingAs(createSeller())->get(route('inventory.characteristics.index'))->assertForbidden();
});
