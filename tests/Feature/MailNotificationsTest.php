<?php

use App\Models\User;
use App\Notifications\NewAccountRegistered;
use App\Services\Core\GoogleSignIn;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Two\User as GoogleUser;

uses(RefreshDatabase::class);

function googleProfile(string $email): GoogleUser
{
    return GoogleUser::fake(['id' => 'google-'.$email, 'name' => 'Rosa Quispe', 'email' => $email, 'email_verified' => true]);
}

// ---------------- Aviso de cuenta nueva ----------------

test('the platform owner is told when someone registers with email and password', function () {
    Notification::fake();
    config(['accounts.signup_notification_emails' => ['dueno@kipusoft.pe']]);

    $this->post(route('register.store'), [
        'name' => 'Rosa Quispe',
        'email' => 'rosa@example.com',
        'password' => 'Clave-Segura-2026',
        'password_confirmation' => 'Clave-Segura-2026',
    ])->assertSessionHasNoErrors();

    $user = User::where('email', 'rosa@example.com')->sole();

    Notification::assertSentOnDemand(
        NewAccountRegistered::class,
        fn (NewAccountRegistered $notification, array $channels, AnonymousNotifiable $notifiable) => $notification->user->is($user)
            && $notifiable->routes['mail'] === ['dueno@kipusoft.pe'],
    );
});

test('the platform owner is told only the first time someone enters with google', function () {
    Notification::fake();
    config(['accounts.signup_notification_emails' => ['dueno@kipusoft.pe']]);

    app(GoogleSignIn::class)->resolve(googleProfile('rosa@gmail.com'));
    app(GoogleSignIn::class)->resolve(googleProfile('rosa@gmail.com'));

    Notification::assertSentOnDemandTimes(NewAccountRegistered::class, 1);
});

test('linking google to an existing account is not a new account', function () {
    Notification::fake();
    config(['accounts.signup_notification_emails' => ['dueno@kipusoft.pe']]);
    User::factory()->create(['email' => 'rosa@gmail.com']);

    app(GoogleSignIn::class)->resolve(googleProfile('rosa@gmail.com'));

    Notification::assertNothingSent();
});

test('without a configured address nobody is notified and the registration still works', function () {
    Notification::fake();
    config(['accounts.signup_notification_emails' => []]);

    event(new Registered($user = User::factory()->create()));

    Notification::assertNotSentTo(new AnonymousNotifiable, NewAccountRegistered::class);
    expect($user->exists)->toBeTrue();
});

test('a mail server failure does not stop the registration', function () {
    config(['accounts.signup_notification_emails' => ['dueno@kipusoft.pe']]);
    Notification::shouldReceive('route')->andThrow(new RuntimeException('SMTP caído'));

    event(new Registered($user = User::factory()->create(['email_verified_at' => now()])));

    expect($user->fresh())->not->toBeNull();
});

test('the new account notice says who registered and how', function () {
    $user = User::factory()->create(['name' => 'Rosa Quispe', 'email' => 'rosa@gmail.com']);
    $user->forceFill(['google_id' => 'google-123'])->save();

    $mail = (new NewAccountRegistered($user))->toMail(new AnonymousNotifiable);
    $body = (string) $mail->render();

    expect($mail->subject)->toBe('Nueva cuenta registrada: Rosa Quispe')
        ->and($body)->toContain('rosa@gmail.com')
        ->toContain('Cuenta de Google')
        ->toContain(route('platform.accounts.index'));
});

// ---------------- Correos de la cuenta, en español ----------------

test('the password reset email is in spanish and links to the reset screen', function () {
    $user = User::factory()->create(['name' => 'Rosa Quispe']);

    $mail = (new ResetPassword('token-123'))->toMail($user);
    $body = (string) $mail->render();

    expect($mail->subject)->toBe('Restablece tu contraseña')
        ->and($mail->actionUrl)->toBe(route('password.reset', ['token' => 'token-123', 'email' => $user->email]))
        ->and($body)->toContain('Hola, Rosa Quispe')
        ->toContain('Restablecer contraseña')
        ->toContain('Saludos,')
        ->toContain('Todos los derechos reservados.')
        ->not->toContain('Regards')
        ->not->toContain('All rights reserved')
        ->not->toContain('trouble clicking');
});

test('the email verification message is in spanish', function () {
    $user = User::factory()->unverified()->create(['name' => 'Rosa Quispe']);

    $mail = (new VerifyEmail)->toMail($user);

    expect($mail->subject)->toBe('Confirma tu correo')
        ->and($mail->actionText)->toBe('Confirmar correo')
        ->and($mail->actionUrl)->toContain('/email/verify/')
        ->and((string) $mail->render())->toContain('Hola, Rosa Quispe')->not->toContain('Hello');
});
