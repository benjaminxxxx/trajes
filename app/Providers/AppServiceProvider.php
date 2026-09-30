<?php

namespace App\Providers;

use App\Http\Middleware\EnsureCurrentAccount;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Models\User;
use App\Services\Core\CurrentAccount;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
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
