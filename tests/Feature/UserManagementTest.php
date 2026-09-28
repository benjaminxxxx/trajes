<?php

use App\Livewire\Core\UserFormModal;
use App\Livewire\Core\UserIndex;
use App\Models\Sale;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('only admins can access the users page', function () {
    $seller = User::factory()->create();
    $seller->assignRole('vendedor');

    $this->actingAs($seller)->get(route('core.users.index'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('core.users.index'))->assertOk();
});

test('admin can create a seller', function () {
    Livewire::actingAs($this->admin)
        ->test(UserFormModal::class)
        ->call('loadUser')
        ->set('name', 'Ana Morales')
        ->set('email', 'ana@correo.com')
        ->set('password', 'secret-password')
        ->set('role', 'vendedor')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('userSaved');

    $user = User::where('email', 'ana@correo.com')->firstOrFail();

    expect($user->hasRole('vendedor'))->toBeTrue();
});

test('admin can change a user role without changing the password', function () {
    $seller = User::factory()->create();
    $seller->assignRole('vendedor');
    $originalPassword = $seller->password;

    Livewire::actingAs($this->admin)
        ->test(UserFormModal::class)
        ->call('loadUser', $seller->id)
        ->set('role', 'admin')
        ->call('save')
        ->assertHasNoErrors();

    $seller->refresh();

    expect($seller->hasRole('admin'))->toBeTrue()
        ->and($seller->hasRole('vendedor'))->toBeFalse()
        ->and($seller->password)->toBe($originalPassword);
});

test('seller modal shows rentals and sales counts', function () {
    $seller = User::factory()->create();
    $seller->assignRole('vendedor');
    createSaleFor($seller);

    Livewire::actingAs($this->admin)
        ->test(UserFormModal::class)
        ->call('loadUser', $seller->id)
        ->assertSet('salesCount', 1)
        ->assertSet('rentalsCount', 0);
});

test('user with activity is soft deleted', function () {
    $seller = User::factory()->create();
    $seller->assignRole('vendedor');
    createSaleFor($seller);

    Livewire::actingAs($this->admin)
        ->test(UserFormModal::class)
        ->call('loadUser', $seller->id)
        ->call('deleteUser');

    $this->assertSoftDeleted($seller);
});

test('user without activity is permanently deleted', function () {
    $seller = User::factory()->create();
    $seller->assignRole('vendedor');

    Livewire::actingAs($this->admin)
        ->test(UserFormModal::class)
        ->call('loadUser', $seller->id)
        ->call('deleteUser');

    $this->assertDatabaseMissing('users', ['id' => $seller->id]);
});

test('admin cannot delete themselves', function () {
    Livewire::actingAs($this->admin)
        ->test(UserFormModal::class)
        ->call('loadUser', $this->admin->id)
        ->call('deleteUser');

    $this->assertNotSoftDeleted($this->admin);
});

test('admin can restore a soft deleted user', function () {
    $seller = User::factory()->create();
    $seller->delete();

    Livewire::actingAs($this->admin)
        ->test(UserIndex::class)
        ->call('restoreUser', $seller->id);

    $this->assertNotSoftDeleted($seller);
});

function createSaleFor(User $user): Sale
{
    return Sale::create([
        'code' => 'V-'.fake()->unique()->numerify('#####'),
        'user_id' => $user->id,
        'sale_date' => now(),
        'subtotal' => 100,
        'total' => 100,
    ]);
}
