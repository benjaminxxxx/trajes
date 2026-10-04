<?php

use App\Livewire\Core\AuditIndex;
use App\Models\AuditLog;
use App\Models\BillingSetting;
use App\Models\Category;
use App\Models\Product;
use App\Services\Finance\ExpenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = createAdmin();
    $this->actingAs($this->admin);
});

test('editing a record logs who changed it and only the fields that changed', function () {
    $product = createProductIn(null)->product;
    $product->update(['name' => 'Terno clásico', 'price' => 100]);
    AuditLog::query()->delete();

    $product->update(['name' => 'Terno slim', 'price' => 150]);

    $log = AuditLog::sole();

    expect($log->event)->toBe('updated')
        ->and($log->auditable_type)->toBe(Product::class)
        ->and($log->auditable_id)->toBe($product->id)
        ->and($log->account_id)->toBe(testAccount()->id)
        ->and($log->user_id)->toBe($this->admin->id)
        ->and($log->old_values)->toEqual(['name' => 'Terno clásico', 'price' => '100.00'])
        ->and($log->new_values)->toEqual(['name' => 'Terno slim', 'price' => 150]);
});

test('saving without changes and changes made by the system leave no audit noise', function () {
    $variant = createProductIn(null, stock: 3);
    AuditLog::query()->delete();

    $variant->product->update(['name' => $variant->product->name]);
    $variant->increment('stock', 2);

    expect(AuditLog::count())->toBe(0);
});

test('deleting a record keeps the values it had', function () {
    $category = Category::create(['name' => 'Ternos', 'slug' => 'ternos']);

    $category->delete();

    $log = AuditLog::where('event', 'deleted')->sole();

    expect($log->modelLabel())->toBe('Categoría')
        ->and($log->old_values['name'])->toBe('Ternos')
        ->and($log->new_values)->toBeNull();
});

test('secret fields are logged masked', function () {
    $setting = BillingSetting::current();
    $setting->update(['api_token' => 'token-secreto-1']);
    AuditLog::query()->delete();

    $setting->update(['api_token' => 'token-secreto-2']);

    $log = AuditLog::sole();

    expect($log->old_values)->toBe(['api_token' => '••••'])
        ->and($log->new_values)->toBe(['api_token' => '••••']);
});

test('the audit panel shows the change with its before and after', function () {
    $expense = app(ExpenseService::class)->register([
        'category' => 'Servicios', 'concept' => 'Luz', 'amount' => 45, 'expense_date' => now(),
    ]);
    app(ExpenseService::class)->cancel($expense, 'Era una prueba');

    $log = AuditLog::sole();

    Livewire::test(AuditIndex::class)
        ->assertSee('Gasto operativo')
        ->assertSee('Motivo de anulación')
        ->set('event', 'deleted')
        ->assertSee('Sin registros')
        ->set('event', 'updated')
        ->call('show', $log->id)
        ->assertSee('Era una prueba');
});

test('the audit of one business is not visible from another', function () {
    createProductIn(null)->product->update(['name' => 'Producto del primer negocio']);
    $foreignLog = AuditLog::sole();

    $other = createTestAccount('Otro negocio');
    actingAsIn(createAdmin($other), $other);

    Livewire::test(AuditIndex::class)
        ->assertSee('Sin registros')
        ->call('show', $foreignLog->id)
        ->assertDontSee('Producto del primer negocio');
});

test('only people with access open the audit panel', function () {
    $this->get(route('core.audit.index'))->assertOk();

    $this->actingAs(createSeller());

    $this->get(route('core.audit.index'))->assertForbidden();
});
