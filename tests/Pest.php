<?php

use App\Models\Account;
use App\Models\AccountMember;
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
use App\Services\Core\AccountService;
use App\Services\Core\CurrentAccount;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
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
    // Todo dato del negocio pertenece a un negocio: cada test arranca con uno
    // de prueba como negocio actual (solo si la base está migrada).
    ->beforeEach(function () {
        if (! Schema::hasTable('accounts')) {
            return;
        }

        (new RolesAndPermissionsSeeder)->run();

        $account = createTestAccount('Negocio de prueba');
        app()->instance('tests.account', $account);
        app(CurrentAccount::class)->set($account);
    })
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
 * El negocio de prueba con el que arranca cada test.
 */
function testAccount(): Account
{
    return app('tests.account');
}

/**
 * Otro negocio, con su dueño (admin). No cambia el negocio actual.
 */
function createTestAccount(string $name = 'Otro negocio'): Account
{
    $owner = User::factory()->create();

    return app(AccountService::class)->createAccount($owner, ['name' => $name], Account::STATUS_ACTIVE);
}

/**
 * Persona miembro de un negocio (el de prueba por defecto) con un rol, o sin
 * rol si $role es null (para probar accesos prohibidos).
 */
function createMember(?string $role = null, ?Account $account = null): User
{
    $account ??= testAccount();
    $user = User::factory()->create();

    if ($role) {
        app(AccountService::class)->addMember($account, $user, $role);
    } else {
        AccountMember::create(['account_id' => $account->id, 'user_id' => $user->id, 'status' => AccountMember::STATUS_ACTIVE]);
    }

    $user->forceFill(['current_account_id' => $account->id])->save();

    return $user;
}

/**
 * Usuario con rol admin en el negocio de prueba (acceso total vía Gate::before).
 */
function createAdmin(?Account $account = null): User
{
    return createMember('admin', $account);
}

/**
 * Usuario con rol vendedor (solo los permisos de config/permissions.php).
 */
function createSeller(?Account $account = null): User
{
    return createMember('vendedor', $account);
}

/**
 * Trabajar como una persona dentro de un negocio (como lo haría el middleware).
 */
function actingAsIn(User $user, Account $account): void
{
    $user->forceFill(['current_account_id' => $account->id])->save();
    test()->actingAs($user);
    app(CurrentAccount::class)->set($account);
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
