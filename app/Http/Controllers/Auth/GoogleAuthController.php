<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Core\GoogleSignIn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

/**
 * Iniciar sesión (o registrarse) con Google.
 */
class GoogleAuthController extends Controller
{
    public function redirect(): SymfonyRedirect
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request, GoogleSignIn $signIn): RedirectResponse
    {
        try {
            $user = $signIn->resolve(Socialite::driver('google')->user());
        } catch (\DomainException $exception) {
            return $this->failed($exception->getMessage());
        } catch (\Throwable $exception) {
            // Cancelado en Google, enlace vencido o estado inválido.
            report($exception);

            return $this->failed('No se pudo iniciar sesión con Google. Inténtalo de nuevo.');
        }

        // Con doble factor activado, Google no lo reemplaza: pasa por el desafío de Fortify.
        if ($user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put(['login.id' => $user->getKey(), 'login.remember' => true]);
            TwoFactorAuthenticationChallenged::dispatch($user);

            return redirect()->route('two-factor.login');
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    protected function failed(string $message): RedirectResponse
    {
        return redirect()->route('login')->with('status', $message);
    }
}
