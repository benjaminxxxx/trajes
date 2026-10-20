<?php

use App\Livewire\Inventory\ProductFormModal;
use App\Livewire\Shared\ProductDetailModal;
use App\Livewire\Shared\ProductGallery;
use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Services\Inventory\ProductSearch;
use App\Services\Inventory\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(createAdmin());

    $this->dresses = Category::create(['name' => 'Vestidos', 'slug' => 'vestidos']);
    $this->suits = Category::create(['name' => 'Ternos', 'slug' => 'ternos']);
    $this->red = Color::create(['name' => 'Rojo', 'hex_code' => '#ff0000']);
    $this->blue = Color::create(['name' => 'Azul', 'hex_code' => '#0000ff']);
    $this->large = Size::create(['name' => 'L', 'type' => 'estandar']);
    $this->medium = Size::create(['name' => 'M', 'type' => 'estandar']);
});

function catalogProduct(Category $category, string $name, string $gender, array $variants = []): Product
{
    $product = Product::create([
        'category_id' => $category->id, 'code' => strtoupper(Str::random(6)), 'name' => $name,
        'slug' => Str::slug($name).'-'.Str::random(4), 'price' => 100, 'gender' => $gender,
    ]);

    foreach ($variants as [$size, $color, $stock]) {
        $variant = ProductVariant::create(['product_id' => $product->id, 'size_id' => $size?->id, 'color_id' => $color?->id, 'sale_price' => 120]);
        $variant->forceFill(['stock' => $stock])->save();
    }

    return $product;
}

function searchNames(string $term): array
{
    return app(ProductSearch::class)->apply(Product::query(), $term)->orderBy('name')->pluck('name')->all();
}

test('every word must match: gender, color, size and manual tags', function () {
    $rocio = catalogProduct($this->dresses, 'Vestido Rocío', 'femenino', [[$this->large, $this->red, 2]]);
    catalogProduct($this->dresses, 'Vestido Pamela', 'femenino', [[$this->medium, $this->blue, 1]]);
    catalogProduct($this->suits, 'Terno Slim', 'masculino', [[$this->large, $this->blue, 3]]);
    app(ProductService::class)->sincronizarEtiquetas($rocio, ['Quinceañera']);

    expect(searchNames('mujer'))->toBe(['Vestido Pamela', 'Vestido Rocío'])
        ->and(searchNames('rojo'))->toBe(['Vestido Rocío'])
        ->and(searchNames('L'))->toBe(['Terno Slim', 'Vestido Rocío'])
        ->and(searchNames('mujer L'))->toBe(['Vestido Rocío'])
        ->and(searchNames('quinceañera'))->toBe(['Vestido Rocío'])
        ->and(searchNames('hombre azul'))->toBe(['Terno Slim'])
        // Una palabra corta no trae todo lo que tenga esa letra en el nombre.
        ->and(searchNames('m'))->toBe(['Vestido Pamela']);
});

test('characteristics are searchable and a new variant is found without retagging', function () {
    $product = catalogProduct($this->dresses, 'Vestido Estrella', 'femenino');
    $product->characteristics()->create(['name' => 'Tela', 'value' => 'Satín']);

    expect(searchNames('satín'))->toBe(['Vestido Estrella'])
        ->and(searchNames('rojo'))->toBe([]);

    // Comprar una variante roja la hace aparecer sola al buscar "rojo".
    ProductVariant::create(['product_id' => $product->id, 'color_id' => $this->red->id]);

    expect(searchNames('rojo'))->toBe(['Vestido Estrella'])
        ->and($product->fresh()->automaticTags())->toBe(['Vestidos', 'Mujer', 'Satín', 'Rojo']);
});

test('the gallery explains each searched word and lets one be removed', function () {
    catalogProduct($this->dresses, 'Vestido Rocío', 'femenino', [[$this->large, $this->red, 2]]);

    Livewire::test(ProductGallery::class, ['mode' => 'sale', 'fieldContext' => 'sale-gallery'])
        ->set('search', 'mujer rojo L')
        ->assertSee('Vestido Rocío')
        ->assertSet('searchChips', [
            ['word' => 'mujer', 'kind' => 'Género'],
            ['word' => 'rojo', 'kind' => 'Color'],
            ['word' => 'l', 'kind' => 'Talla'],
        ])
        ->call('removeSearchWord', 'rojo')
        ->assertSet('search', 'mujer l');
});

test('manual tags are saved from the product form without duplicates', function () {
    $product = catalogProduct($this->dresses, 'Vestido Silvana', 'femenino');

    Livewire::test(ProductFormModal::class)
        ->call('open', $product->id)
        ->set('newTag', 'Quinceañera')->call('addTag')
        ->set('newTag', 'quinceañera')->call('addTag')
        ->set('newTag', 'Señora')->call('addTag')
        ->call('save')
        ->assertHasNoErrors();

    expect($product->tags()->pluck('name')->all())->toBe(['Quinceañera', 'Señora']);

    Livewire::test(ProductFormModal::class)
        ->call('open', $product->id)
        ->assertSet('tags', ['Quinceañera', 'Señora'])
        ->call('removeTag', 0)
        ->call('save');

    expect($product->tags()->pluck('name')->all())->toBe(['Señora']);
});

test('the reel only sends stock that exists', function () {
    $withStock = catalogProduct($this->dresses, 'Vestido Rocío', 'femenino', [[$this->large, $this->red, 2], [$this->medium, $this->red, 0]]);
    $withoutStock = catalogProduct($this->dresses, 'Vestido Nuevo', 'femenino');

    Livewire::test(ProductDetailModal::class)
        ->call('open', $withStock->id, 'sale', 'sale-gallery')
        ->assertDispatched('product-reel-open', fn (string $event, array $params) => $params['product']['variants'] === [['label' => 'L · Rojo', 'hex' => '#ff0000', 'qty' => 2]]
            && $params['product']['available'] === 2
            && (float) $params['product']['price'] === 120.0
            && $params['product']['context'] === 'sale-gallery');

    Livewire::test(ProductDetailModal::class)
        ->call('open', $withoutStock->id, 'sale')
        ->assertDispatched('product-reel-open', fn (string $event, array $params) => $params['product']['variants'] === []);
});
