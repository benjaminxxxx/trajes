<?php

use App\Livewire\Platform\AccountIndex;
use App\Models\Account;
use App\Models\User;
use App\Services\Core\AccountService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createPlatformAdmin(): User
{
    $user = User::factory()->create();
    $user->forceFill(['is_platform_admin' => true])->save();

    return $user;
}

test('the platform admin needs no business and lands on the platform panel', function () {
    $this->actingAs(createPlatformAdmin())
        ->get(route('dashboard'))
        ->assertRedirect(route('platform.accounts.index'));

    $this->get(route('platform.accounts.index'))->assertOk()->assertSee(testAccount()->name);
});

test('only the platform admin opens the platform panel', function () {
    $this->actingAs(createAdmin())->get(route('platform.accounts.index'))->assertForbidden();
});

test('the panel lists every business with its owner and usage', function () {
    $other = createTestAccount('Alquileres Lima');
    createProductIn(null);

    Livewire::actingAs(createPlatformAdmin())
        ->test(AccountIndex::class)
        ->assertSee('Alquileres Lima')
        ->assertSee($other->owner->email)
        ->assertSee(testAccount()->name)
        ->assertViewHas('usage', fn (array $usage) => $usage['products'][testAccount()->id] === 1);
});

test('the platform registers the business of a client, whose owner can then sign in', function () {
    Notification::fake();

    Livewire::actingAs(createPlatformAdmin())
        ->test(AccountIndex::class)
        ->call('create')
        ->set('name', 'Glamour & Estilos')
        ->set('ownerName', 'Rosa')
        ->set('ownerEmail', 'glamour@gmail.com')
        ->call('save')
        ->assertHasNoErrors();

    $account = Account::where('name', 'Glamour & Estilos')->firstOrFail();
    $owner = User::where('email', 'glamour@gmail.com')->firstOrFail();

    expect($account->isOwnedBy($owner))->toBeTrue()
        ->and($account->status)->toBe(Account::STATUS_ACTIVE)
        ->and(app(AccountService::class)->roleIn($account, $owner))->toBe('admin');

    Notification::assertSentTo($owner, ResetPassword::class);

    auth()->logout();
    $this->actingAs($owner->fresh())->get(route('dashboard'))->assertOk();
});

test('the platform renames a business and changes its owner by email', function () {
    $previousOwner = testAccount()->owner;

    Livewire::actingAs(createPlatformAdmin())
        ->test(AccountIndex::class)
        ->call('edit', testAccount()->id)
        ->set('name', 'Glamour & Estilos')
        ->call('save')
        ->call('openOwner', testAccount()->id)
        ->set('ownerName', 'Rosa')
        ->set('ownerEmail', 'glamour@gmail.com')
        ->call('changeOwner')
        ->assertHasNoErrors();

    $account = testAccount()->fresh();
    $newOwner = User::where('email', 'glamour@gmail.com')->firstOrFail();

    expect($account->name)->toBe('Glamour & Estilos')
        ->and($account->isOwnedBy($newOwner))->toBeTrue()
        ->and(app(AccountService::class)->roleIn($account, $previousOwner))->toBe('admin');
});

test('blocking a business keeps everyone out until it is unblocked', function () {
    $seller = createSeller();
    $panel = Livewire::actingAs(createPlatformAdmin())->test(AccountIndex::class);

    $panel->call('openBlock', testAccount()->id)->set('blockReason', 'Falta de pago')->call('block');

    expect(testAccount()->fresh()->isBlocked())->toBeTrue()
        ->and(testAccount()->fresh()->blocked_reason)->toBe('Falta de pago');

    auth()->logout();
    $this->actingAs($seller)->get(route('dashboard'))->assertRedirect(route('login'));

    $panel->call('unblock', testAccount()->id);

    $this->actingAs($seller->fresh())->get(route('dashboard'))->assertOk();
});
