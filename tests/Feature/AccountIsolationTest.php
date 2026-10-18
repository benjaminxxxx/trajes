<?php

use App\Livewire\Core\AccountPicker;
use App\Livewire\Inventory\ProductFormModal;
use App\Livewire\Inventory\ProductIndex;
use App\Models\Account;
use App\Models\BelongsToAccount;
use App\Models\Client;
use App\Models\Product;
use App\Models\User;
use App\Services\Core\AccountRule;
use App\Services\Core\AccountService;
use App\Services\Core\CurrentAccount;
use App\Services\Documents\ReceiptService;
use App\Services\Sale\SaleService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Tablas de la plataforma (no pertenecen a un negocio).
 */
const PLATFORM_TABLES = [
    'accounts', 'account_user', 'users', 'passkeys', 'password_reset_tokens', 'sessions',
    'permissions', 'roles', 'model_has_permissions', 'model_has_roles', 'role_has_permissions',
    'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'migrations',
];

/**
 * Pivotes entre dos modelos del mismo negocio: se filtran a través de ellos.
 */
const PIVOT_TABLES = ['asset_category', 'attribute_category', 'category_color', 'category_size'];

/**
 * Modelos que no llevan el filtro por negocio, con su motivo.
 */
const MODELS_WITHOUT_ACCOUNT_SCOPE = [
    'User' => 'persona global: puede pertenecer a varios negocios',
    'Account' => 'es el negocio',
    'AccountMember' => 'pertenencia persona ↔ negocio',
    'AuditLog' => 'account_id opcional (acciones de la plataforma)',
];

function productIn(Account $account, string $name): Product
{
    return app(CurrentAccount::class)->runAs($account, function () use ($name) {
        $product = createProductIn(null, stock: 1)->product;
        $product->update(['name' => $name]);

        return $product;
    });
}

// ---------------- Guardianes: nada del negocio queda sin filtro ----------------

test('every business table has an account_id column', function () {
    $tables = collect(Schema::getTables())->pluck('name')
        ->reject(fn (string $table) => str_starts_with($table, 'sqlite_'))
        ->diff([...PLATFORM_TABLES, ...PIVOT_TABLES]);

    $missing = $tables->reject(fn (string $table) => Schema::hasColumn($table, 'account_id'))->values()->all();

    expect($missing)->toBe([], 'Tablas sin account_id: agrégala o decláralas como plataforma/pivote en este test.');
});

test('every business model uses BelongsToAccount', function () {
    $missing = collect(glob(app_path('Models/*.php')))
        ->map(fn (string $path) => pathinfo($path, PATHINFO_FILENAME))
        ->filter(fn (string $name) => is_subclass_of("App\\Models\\{$name}", Model::class))
        ->reject(fn (string $name) => array_key_exists($name, MODELS_WITHOUT_ACCOUNT_SCOPE))
        ->reject(fn (string $name) => in_array(BelongsToAccount::class, class_uses_recursive("App\\Models\\{$name}")))
        ->values()->all();

    expect($missing)->toBe([], 'Modelos sin BelongsToAccount.');
});

// ---------------- Aislamiento de datos ----------------

test('a business never sees the data of another business', function () {
    $other = createTestAccount('Otro negocio');
    $foreign = productIn($other, 'Terno del vecino');
    $own = productIn(testAccount(), 'Terno propio');

    $this->actingAs(createAdmin());

    expect(Product::pluck('id')->all())->toBe([$own->id]);

    Livewire::test(ProductIndex::class)->assertSee('Terno propio')->assertDontSee('Terno del vecino');

    expect(fn () => Livewire::test(ProductFormModal::class)->call('open', $foreign->id))
        ->toThrow(ModelNotFoundException::class);
});

test('records get the current business and cannot be moved to another one', function () {
    $client = createClient();

    expect($client->account_id)->toBe(testAccount()->id);
    expect(fn () => $client->forceFill(['account_id' => createTestAccount()->id])->save())
        ->toThrow(LogicException::class);

    app(CurrentAccount::class)->forget();

    expect(fn () => createClient())->toThrow(LogicException::class);
});

test('someone logged in without a valid business sees nothing', function () {
    productIn(testAccount(), 'Terno');
    $stranger = User::factory()->create();

    $this->actingAs($stranger);
    app(CurrentAccount::class)->forget();

    expect(Product::count())->toBe(0);
    $this->get(route('dashboard'))->assertRedirect(route('accounts.select'));
});

test('forms reject ids that belong to another business', function () {
    $foreignClient = app(CurrentAccount::class)->runAs(createTestAccount(), fn () => createClient());
    $ownClient = createClient();

    expect(Validator::make(['id' => $foreignClient->id], ['id' => [AccountRule::exists('clients')]])->fails())->toBeTrue()
        ->and(Validator::make(['id' => $ownClient->id], ['id' => [AccountRule::exists('clients')]])->passes())->toBeTrue();
});

test('two businesses can have the same sale code and the same client document', function () {
    $other = createTestAccount();
    $register = function () {
        $variant = createProductIn(null, stock: 1);
        $variant->product->update(['allow_rental' => false]);

        return app(SaleService::class)->register(
            ['document_type' => 'nota_venta', 'sale_date' => now()->toDateString()],
            [['product_id' => $variant->product_id, 'quantity' => 1, 'unit_price' => 100]],
        );
    };

    $this->actingAs(createAdmin());
    $ownSale = $register();
    $otherSale = app(CurrentAccount::class)->runAs($other, $register);

    Client::create(['uuid' => Str::uuid(), 'document_type' => 'DNI', 'document_number' => '12345678', 'display_name' => 'Ana']);
    app(CurrentAccount::class)->runAs($other, fn () => Client::create(['uuid' => Str::uuid(), 'document_type' => 'DNI', 'document_number' => '12345678', 'display_name' => 'Ana']));

    expect($ownSale->code)->toBe($otherSale->code)
        ->and(Client::withoutAccountScope()->where('document_number', '12345678')->count())->toBe(2);
});

// ---------------- Personas en varios negocios ----------------

test('the same person can be admin in one business and seller in another', function () {
    $other = createTestAccount('Alquileres Lima');
    $person = createAdmin();
    app(AccountService::class)->addMember($other, $person, 'vendedor');

    actingAsIn($person, testAccount());
    expect($person->can('ventas.anular'))->toBeTrue();

    actingAsIn($person, $other);
    expect($person->fresh()->can('ventas.anular'))->toBeFalse()
        ->and($person->fresh()->can('ventas.registrar'))->toBeTrue();
});

test('a person with several businesses chooses where to enter', function () {
    $other = createTestAccount('Alquileres Lima');
    $person = createSeller();
    app(AccountService::class)->addMember($other, $person, 'vendedor');
    productIn($other, 'Terno de Lima');

    $this->actingAs($person);
    app(CurrentAccount::class)->forget();

    Livewire::test(AccountPicker::class)
        ->assertSee('Alquileres Lima')
        ->assertSee(testAccount()->name)
        ->call('enter', $other->id)
        ->assertRedirect(route('dashboard'));

    expect($person->fresh()->current_account_id)->toBe($other->id);

    $this->get(route('inventory.physical-items.index'))->assertForbidden();
    $this->get(route('sale.sales.create'))->assertOk();
});

test('a person cannot enter a business they do not belong to', function () {
    $foreign = createTestAccount();
    $person = createSeller();

    $this->actingAs($person);

    Livewire::test(AccountPicker::class)->call('enter', $foreign->id)->assertHasErrors('account');

    expect($person->fresh()->current_account_id)->toBe(testAccount()->id);
});

test('a new user without a business registers one and becomes its owner', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('accounts.select'));

    Livewire::test(AccountPicker::class)
        ->assertSet('isCreating', true)
        ->set('name', 'Trajes Elegancia')
        ->call('create')
        ->assertRedirect(route('dashboard'));

    $account = Account::where('name', 'Trajes Elegancia')->firstOrFail();

    expect($account->isOwnedBy($user))->toBeTrue()
        ->and($account->status)->toBe(Account::STATUS_TRIAL)
        ->and(app(AccountService::class)->roleIn($account, $user))->toBe('admin');

    $this->actingAs($user->fresh())->get(route('core.users.index'))->assertOk();
});

// ---------------- Bloqueo ----------------

test('nobody enters a blocked business, not even its sellers', function () {
    $seller = createSeller();
    testAccount()->forceFill(['status' => Account::STATUS_BLOCKED])->save();

    $this->actingAs($seller)->get(route('dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status');

    $this->assertGuest();
});

test('a person whose business was blocked keeps working in their other businesses', function () {
    $other = createTestAccount('Alquileres Lima');
    $person = createSeller();
    app(AccountService::class)->addMember($other, $person, 'vendedor');
    testAccount()->forceFill(['status' => Account::STATUS_BLOCKED])->save();

    $this->actingAs($person)->get(route('dashboard'))->assertOk();

    expect($person->fresh()->current_account_id)->toBe($other->id);
});

// ---------------- Comprobantes ----------------

test('the public receipt link opens in its own business even if the viewer works in another', function () {
    Storage::fake('local');
    $variant = createProductIn(null, stock: 1);
    $variant->product->update(['allow_rental' => false]);
    $sale = app(SaleService::class)->register(
        ['document_type' => 'nota_venta', 'sale_date' => now()->toDateString()],
        [['product_id' => $variant->product_id, 'quantity' => 1, 'unit_price' => 100]],
    );
    $publicUrl = app(ReceiptService::class)->publicUrl($sale);

    actingAsIn(createAdmin(createTestAccount()), Account::latest('id')->first());

    $this->get($publicUrl)->assertOk()->assertHeader('Content-Type', 'application/pdf');
    Storage::disk('local')->assertExists("accounts/{$sale->account_id}/receipts/ventas/{$sale->code}.pdf");
});
