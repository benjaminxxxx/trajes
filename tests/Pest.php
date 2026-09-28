<?php

use App\Models\Category;
use App\Models\Client;
use App\Models\PhysicalProduct;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Rental;
use App\Models\RentalDetail;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Usuario con rol admin (acceso total vía Gate::before). Siembra los roles si hace falta.
 */
function createAdmin(): User
{
    if (! Role::where('name', 'admin')->exists()) {
        (new RolesAndPermissionsSeeder)->run();
    }

    return tap(User::factory()->create())->assignRole('admin');
}

/**
 * Usuario con rol vendedor (solo los permisos de config/permissions.php).
 */
function createSeller(): User
{
    if (! Role::where('name', 'vendedor')->exists()) {
        (new RolesAndPermissionsSeeder)->run();
    }

    return tap(User::factory()->create())->assignRole('vendedor');
}

function createClient(): Client
{
    return Client::create([
        'uuid' => (string) Str::uuid(),
        'document_type' => 'DNI',
        'document_number' => fake()->unique()->numerify('########'),
        'display_name' => fake()->name(),
    ]);
}

function createProductIn(?Category $category, int $stock = 0): ProductVariant
{
    $code = Str::random(8);

    $product = Product::create([
        'category_id' => $category?->id,
        'code' => $code,
        'name' => "Producto {$code}",
        'slug' => Str::slug($code),
    ]);

    $variant = ProductVariant::create(['product_id' => $product->id]);
    $variant->forceFill(['stock' => $stock])->save();

    return $variant;
}

function createPhysicalUnit(ProductVariant $variant, string $status = 'available'): PhysicalProduct
{
    return PhysicalProduct::create([
        'code' => 'P-'.Str::random(6),
        'product_variant_id' => $variant->id,
        'status' => $status,
    ]);
}

function rentVariant(ProductVariant $variant, string $date, string $status = 'returned'): void
{
    $rental = Rental::create([
        'code' => 'A-'.Str::random(6),
        'client_id' => createClient()->id,
        'pickup_date' => $date,
        'expected_return_date' => $date,
        'status' => $status,
    ]);

    RentalDetail::create([
        'rental_id' => $rental->id,
        'physical_product_id' => createPhysicalUnit($variant)->id,
        'price' => 50,
    ]);
}

function sellVariant(ProductVariant $variant, string $date, int $quantity, string $status = 'completed'): void
{
    $sale = Sale::create([
        'code' => 'V-'.Str::random(6),
        'sale_date' => $date,
        'subtotal' => 100,
        'total' => 100,
        'status' => $status,
    ]);

    SaleDetail::create([
        'sale_id' => $sale->id,
        'product_variant_id' => $variant->id,
        'quantity' => $quantity,
        'unit_cost' => 10,
        'unit_price' => 20,
        'subtotal' => 20 * $quantity,
    ]);
}
