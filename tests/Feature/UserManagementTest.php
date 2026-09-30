<?php

use App\Livewire\Core\UserFormModal;
use App\Livewire\Core\UserIndex;
use App\Models\AccountMember;
use App\Models\Sale;
use App\Models\User;
use App\Services\Core\AccountService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->owner = testAccount()->owner;
    $this->admin = createAdmin();
});

test('only admins can access the users page', function () {
    $this->actingAs(createSeller())->get(route('core.users.index'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('core.users.index'))->assertOk();
});

test('admin gives access to a new person, who receives a link to create their password', function () {
    Notification::fake();

    Livewire::actingAs($this->admin)
        ->test(UserFormModal::class)
        ->call('loadUser')
        ->set('name', 'Ana Morales')
        ->set('email', 'Ana@Correo.com')
        ->set('role', 'vendedor')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('userSaved');

    $user = User::where('email', 'ana@correo.com')->firstOrFail();

    expect($user->hasRole('vendedor'))->toBeTrue()
        ->and(app(AccountService::class)->isActiveMember(testAccount(), $user))->toBeTrue();

    Notification::assertSentTo($user, ResetPassword::class);
});

test('a person already using another business joins with the same user and password', function () {
    Notification::fake();
    $otherBusiness = createTestAccount('Alquileres Lima');
    $seller = createSeller($otherBusiness);
    $password = $seller->password;

    Livewire::actingAs($this->admin)
        ->test(UserFormModal::class)
        ->call('loadUser')
        ->set('name', 'Otro nombre')
        ->set('email', $seller->email)
        ->set('role', 'vendedor')
        ->call('save')
        ->assertHasNoErrors();

    expect(User::where('email', $seller->email)->count())->toBe(1)
        ->and($seller->fresh()->password)->toBe($password)
        ->and($seller->fresh()->name)->not->toBe('Otro nombre')
        ->and(app(AccountService::class)->isActiveMember($otherBusiness, $seller))->toBeTrue()
        ->and(app(AccountService::class)->isActiveMember(testAccount(), $seller))->toBeTrue();

    Notification::assertNothingSent();
});

test('adding someone who is already a member is rejected', function () {
    $seller = createSeller();

    Livewire::actingAs($this->admin)
        ->test(UserFormModal::class)
        ->call('loadUser')
        ->set('name', $seller->name)
        ->set('email', $seller->email)
        ->call('save')
        ->assertHasErrors('email');
});

test('admin can change a member role without changing the password', function () {
    $seller = createSeller();
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
    $seller = createSeller();
    createSaleFor($seller);

    Livewire::actingAs($this->admin)
        ->test(UserFormModal::class)
        ->call('loadUser', $seller->id)
        ->assertSet('salesCount', 1)
        ->assertSet('rentalsCount', 0);
});

test('a retired member keeps their user and history but can no longer enter the business', function () {
    $seller = createSeller();
    $sale = createSaleFor($seller);

    Livewire::actingAs($this->admin)
        ->test(UserFormModal::class)
        ->call('loadUser', $seller->id)
        ->call('revoke');

    expect(User::find($seller->id))->not->toBeNull()
        ->and($sale->fresh()->user_id)->toBe($seller->id)
        ->and(AccountMember::where('user_id', $seller->id)->value('status'))->toBe(AccountMember::STATUS_REVOKED);

    $this->actingAs($seller->fresh())->get(route('dashboard'))->assertRedirect(route('accounts.select'));
});

test('an admin cannot retire or demote the owner, nor retire themselves', function () {
    Livewire::actingAs($this->admin)
        ->test(UserFormModal::class)
        ->call('loadUser', $this->owner->id)
        ->assertSet('isOwner', true)
        ->call('revoke')
        ->set('role', 'vendedor')
        ->call('save')
        ->assertHasErrors('role');

    Livewire::actingAs($this->admin)
        ->test(UserFormModal::class)
        ->call('loadUser', $this->admin->id)
        ->call('revoke');

    expect(app(AccountService::class)->isActiveMember(testAccount(), $this->owner))->toBeTrue()
        ->and(app(AccountService::class)->roleIn(testAccount(), $this->owner))->toBe('admin')
        ->and(app(AccountService::class)->isActiveMember(testAccount(), $this->admin))->toBeTrue();
});

test('only the owner can transfer the ownership, and only to an active admin', function () {
    $seller = createSeller();

    expect(fn () => app(AccountService::class)->transferOwnership(testAccount(), $this->admin, $this->admin))
        ->toThrow(DomainException::class);
    expect(fn () => app(AccountService::class)->transferOwnership(testAccount(), $seller, $this->owner))
        ->toThrow(DomainException::class);

    Livewire::actingAs($this->owner)
        ->test(UserFormModal::class)
        ->call('loadUser', $this->admin->id)
        ->call('transferOwnership');

    expect(testAccount()->fresh()->owner_id)->toBe($this->admin->id)
        ->and(app(AccountService::class)->roleIn(testAccount(), $this->owner))->toBe('admin');
});

test('admin can give access back to a retired member', function () {
    $seller = createSeller();
    app(AccountService::class)->revoke(testAccount(), $seller, $this->admin);

    Livewire::actingAs($this->admin)
        ->test(UserIndex::class)
        ->set('showArchived', true)
        ->assertSee($seller->email)
        ->call('restoreUser', $seller->id);

    expect(app(AccountService::class)->isActiveMember(testAccount(), $seller))->toBeTrue()
        ->and(app(AccountService::class)->roleIn(testAccount(), $seller))->toBe('vendedor');
});

test('the users list only shows people of the current business', function () {
    $outsider = createSeller(createTestAccount());

    Livewire::actingAs($this->admin)
        ->test(UserIndex::class)
        ->assertSee($this->admin->email)
        ->assertDontSee($outsider->email);
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
