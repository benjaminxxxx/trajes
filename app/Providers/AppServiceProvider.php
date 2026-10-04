<?php

namespace App\Providers;

use App\Http\Middleware\EnsureCurrentAccount;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Models\User;
use App\Notifications\NewAccountRegistered;
use App\Services\Core\CurrentAccount;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Un negocio actual por request (y por job en los workers).
        $this->app->scoped(CurrentAccount::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Admin del negocio actual: acceso total dentro de ese negocio. Sin
        // negocio no hay rol (spatie filtra los roles por account_id).
        Gate::before(function ($user, string $ability) {
            return app(CurrentAccount::class)->id() !== null && $user->hasRole('admin') ? true : null;
        });

        Livewire::addPersistentMiddleware([EnsureCurrentAccount::class, EnsurePlatformAdmin::class]);

        // Con contraseña, Google o passkey: el correo decide si es administrador de la plataforma.
        Event::listen(Login::class, fn (Login $event) => $event->user instanceof User ? $event->user->syncPlatformAdmin() : null);

        $this->configureMail();
    }

    /**
     * Correos de la cuenta en español y con el nombre de la persona, y aviso
     * al dueño de la plataforma cuando alguien se registra por primera vez.
     */
    protected function configureMail(): void
    {
        ResetPassword::toMailUsing(fn (object $notifiable, string $token) => (new MailMessage)
            ->subject('Restablece tu contraseña')
            ->greeting('Hola, '.$notifiable->name)
            ->line('Recibimos una solicitud para restablecer la contraseña de tu cuenta en '.config('app.name').'.')
            ->action('Restablecer contraseña', url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false)))
            ->line('Este enlace vence en '.config('auth.passwords.'.config('auth.defaults.passwords').'.expire').' minutos.')
            ->line('Si no solicitaste este cambio, puedes ignorar este mensaje: tu contraseña seguirá siendo la misma.'));

        VerifyEmail::toMailUsing(fn (object $notifiable, string $url) => (new MailMessage)
            ->subject('Confirma tu correo')
            ->greeting('Hola, '.$notifiable->name)
            ->line('Gracias por registrarte en '.config('app.name').'. Confirma tu correo para terminar de activar tu cuenta.')
            ->action('Confirmar correo', $url)
            ->line('Si no creaste esta cuenta, puedes ignorar este mensaje.'));

        Event::listen(Registered::class, function (Registered $event) {
            $recipients = config('accounts.signup_notification_emails');

            if (! $event->user instanceof User || $recipients === []) {
                return;
            }

            // Un fallo del servidor de correo no debe impedir que la persona se registre.
            try {
                Notification::route('mail', $recipients)->notify(new NewAccountRegistered($event->user));
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(
            fn (): ?Password => app()->isProduction()
                ? Password::min(12)
                    ->mixedCase()
                    ->letters()
                    ->numbers()
                    ->symbols()
                    ->uncompromised()
                : null,
        );
    }
}
