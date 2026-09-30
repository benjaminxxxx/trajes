<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;

uses(RefreshDatabase::class);

function fakeGoogleUser(array $attributes = []): GoogleUser
{
    $googleUser = GoogleUser::fake([
        'id' => 'google-123',
        'name' => 'Rosa Quispe',
        'email' => 'rosa@gmail.com',
        'avatar' => 'https://lh3.googleusercontent.com/rosa.jpg',
        'email_verified' => true,
        ...$attributes,
    ]);

    Socialite::fake('google', $googleUser);

    return $googleUser;
}

test('the login button sends the person to google', function () {
    $this->get(route('login'))->assertOk()->assertSee(route('auth.google'), false);

    expect($this->get(route('auth.google'))->headers->get('Location'))->toStartWith('https://accounts.google.com/');
});

test('a new person is created verified and goes on to register their business', function () {
    fakeGoogleUser();

    $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard'));

    $user = User::where('email', 'rosa@gmail.com')->firstOrFail();

    $this->assertAuthenticatedAs($user);
    expect($user->google_id)->toBe('google-123')
        ->and($user->name)->toBe('Rosa Quispe')
        ->and($user->hasVerifiedEmail())->toBeTrue();

    $this->get(route('dashboard'))->assertRedirect(route('accounts.select'));
});

test('an existing admin or seller signs in with google and their google account gets linked', function (string $role) {
    $member = createMember($role);
    fakeGoogleUser(['email' => strtoupper($member->email), 'name' => 'Otro nombre']);

    $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($member);
    expect($member->fresh()->google_id)->toBe('google-123')
        ->and($member->fresh()->name)->toBe($member->name)
        ->and(User::where('email', $member->email)->count())->toBe(1);

    $this->get(route('dashboard'))->assertOk();
})->with(['admin', 'vendedor']);

test('a linked person is recognized by google id even if their email changed', function () {
    $member = createSeller();
    $member->forceFill(['google_id' => 'google-123'])->save();
    fakeGoogleUser(['email' => 'nuevo-correo@gmail.com']);

    $this->get(route('auth.google.callback'));

    $this->assertAuthenticatedAs($member);
});

test('google accounts without a verified email are rejected', function () {
    fakeGoogleUser(['email_verified' => false]);

    $this->get(route('auth.google.callback'))->assertRedirect(route('login'))->assertSessionHas('status');

    $this->assertGuest();
    expect(User::where('email', 'rosa@gmail.com')->exists())->toBeFalse();
});

test('an email already linked to another google account is rejected', function () {
    $member = createSeller();
    $member->forceFill(['google_id' => 'google-original'])->save();
    fakeGoogleUser(['id' => 'google-intruso', 'email' => $member->email]);

    $this->get(route('auth.google.callback'))->assertRedirect(route('login'));

    $this->assertGuest();
});

test('google does not skip two factor authentication', function () {
    $member = createSeller();
    $member->forceFill(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()])->save();
    fakeGoogleUser(['email' => $member->email]);

    $this->get(route('auth.google.callback'))->assertRedirect(route('two-factor.login'));

    $this->assertGuest();
});

test('the platform admin is recognized by the configured email on any login', function () {
    config(['accounts.platform_admins' => ['benuserxxx@gmail.com']]);
    fakeGoogleUser(['email' => 'benuserxxx@gmail.com']);

    $this->get(route('auth.google.callback'));

    expect(User::where('email', 'benuserxxx@gmail.com')->value('is_platform_admin'))->toBeTrue();

    $regular = createSeller();
    $regular->forceFill(['is_platform_admin' => true])->save();
    auth()->logout();

    $this->post(route('login.store'), ['email' => $regular->email, 'password' => 'password']);

    expect($regular->fresh()->is_platform_admin)->toBeFalse();
});

test('whoever registers with the form must verify their email first', function () {
    $this->post(route('register.store'), [
        'name' => 'Luis Pérez',
        'email' => 'luis@correo.com',
        'password' => 'clave-segura-123',
        'password_confirmation' => 'clave-segura-123',
    ]);

    $user = User::where('email', 'luis@correo.com')->firstOrFail();

    expect($user->hasVerifiedEmail())->toBeFalse();
    $this->get(route('dashboard'))->assertRedirect(route('verification.notice'));
    $this->get(route('accounts.select'))->assertRedirect(route('verification.notice'));
});
