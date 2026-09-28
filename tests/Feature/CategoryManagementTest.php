<?php

use App\Livewire\Inventory\CategoryFormModal;
use App\Livewire\Inventory\CategoryYearReport;
use App\Models\Category;
use App\Models\Color;
use App\Models\Size;
use App\Services\Inventory\CategoryReportService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = createAdmin();
});

test('sellers cannot manage categories', function () {
    $this->actingAs(createSeller())
        ->get(route('inventory.categories.index'))
        ->assertForbidden();

    Livewire::actingAs(createSeller())
        ->test(CategoryFormModal::class)
        ->call('loadCategory')
        ->set('name', 'Ternos')
        ->call('save')
        ->assertForbidden();
});

test('categories page renders', function () {
    Category::create(['name' => 'Ternos', 'slug' => 'ternos']);

    $this->actingAs($this->admin)
        ->get(route('inventory.categories.index'))
        ->assertOk()
        ->assertSee('Ternos');
});

test('category is created with its sizes and colors', function () {
    $size = Size::create(['name' => 'M']);
    $color = Color::create(['name' => 'Negro', 'hex_code' => '#000000']);

    Livewire::actingAs($this->admin)
        ->test(CategoryFormModal::class)
        ->call('loadCategory')
        ->set('name', 'Ternos de Caballero')
        ->call('toggleSize', $size->id)
        ->call('toggleColor', $color->id)
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('categorySaved');

    $category = Category::where('slug', 'ternos-de-caballero')->firstOrFail();

    expect($category->sizes->pluck('id')->all())->toBe([$size->id])
        ->and($category->colors->pluck('id')->all())->toBe([$color->id]);
});

test('category name must be unique including archived ones', function () {
    Category::create(['name' => 'Ternos', 'slug' => 'ternos'])->delete();

    Livewire::actingAs($this->admin)
        ->test(CategoryFormModal::class)
        ->call('loadCategory')
        ->set('name', 'Ternos')
        ->call('save')
        ->assertHasErrors('name');
});

test('category cannot be its own parent', function () {
    $category = Category::create(['name' => 'Ternos', 'slug' => 'ternos']);

    Livewire::actingAs($this->admin)
        ->test(CategoryFormModal::class)
        ->call('loadCategory', $category->id)
        ->set('parent_id', $category->id)
        ->call('save')
        ->assertHasErrors('parent_id');
});

test('category with products is soft deleted', function () {
    $category = Category::create(['name' => 'Ternos', 'slug' => 'ternos']);
    createProductIn($category);

    Livewire::actingAs($this->admin)
        ->test(CategoryFormModal::class)
        ->call('loadCategory', $category->id)
        ->call('deleteCategory');

    $this->assertSoftDeleted($category);
});

test('category without products is permanently deleted', function () {
    $category = Category::create(['name' => 'Ternos', 'slug' => 'ternos']);

    Livewire::actingAs($this->admin)
        ->test(CategoryFormModal::class)
        ->call('loadCategory', $category->id)
        ->call('deleteCategory');

    $this->assertDatabaseMissing('categories', ['id' => $category->id]);
});

test('report counts rented and sold units per month excluding cancelled operations', function () {
    $category = Category::create(['name' => 'Ternos', 'slug' => 'ternos']);
    $other = Category::create(['name' => 'Vestidos', 'slug' => 'vestidos']);
    $variant = createProductIn($category);
    $otherVariant = createProductIn($other);

    rentVariant($variant, '2026-03-10');
    rentVariant($variant, '2026-03-20');
    rentVariant($variant, '2026-03-25', 'cancelled');
    rentVariant($otherVariant, '2026-03-25');
    sellVariant($variant, '2026-05-02', quantity: 3);
    sellVariant($variant, '2026-05-03', quantity: 2, status: 'cancelled');
    sellVariant($variant, '2025-05-03', quantity: 1);

    $matrix = app(CategoryReportService::class)->monthlyMatrix(2026, $category->id);

    expect($matrix['rentals'][3])->toBe(2)
        ->and(array_sum($matrix['rentals']))->toBe(2)
        ->and($matrix['sales'][5])->toBe(3)
        ->and(array_sum($matrix['sales']))->toBe(3);

    $units = app(CategoryReportService::class)->unitsByCategory(2026);

    expect($units['rentals'])->toBe([$category->id => 2, $other->id => 1])
        ->and($units['sales'])->toBe([$category->id => 3]);
});

test('report modal renders products per category', function () {
    $category = Category::create(['name' => 'Ternos', 'slug' => 'ternos']);
    createProductIn($category);

    Livewire::actingAs($this->admin)
        ->test(CategoryYearReport::class)
        ->call('open')
        ->assertSee('Ternos')
        ->assertSee('Alquileres')
        ->assertSee('Ventas');
});
