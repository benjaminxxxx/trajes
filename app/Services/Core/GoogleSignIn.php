<?php

namespace App\Services\Core;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as GoogleUser;

/**
 * Encuentra, vincula o crea al usuario que entra con Google.
 *
 * - Ya vinculado (google_id) → es él, aunque haya cambiado su correo.
 * - Existe con ese correo (admin, vendedor o invitado) → se vincula su Google.
 * - No existe → se crea con los datos de Google. Aún no tiene negocio: el
 *   middleware lo lleva a registrar el suyo, del que queda como dueño.
 *
 * En todos los casos el correo queda verificado: Google ya lo verificó.
 */
class GoogleSignIn
{
    public function resolve(GoogleUser $googleUser): User
    {
        $email = Str::lower(trim((string) $googleUser->getEmail()));

        if ($email === '' || ! ($googleUser->getRaw()['email_verified'] ?? false)) {
            throw new \DomainException('Tu cuenta de Google no tiene un correo verificado.');
        }

        $user = User::withTrashed()->where('google_id', $googleUser->getId())->first()
            ?? User::withTrashed()->where('email', $email)->first();

        if ($user && $user->google_id && $user->google_id !== (string) $googleUser->getId()) {
            throw new \DomainException('Este correo ya está vinculado a otra cuenta de Google.');
        }

        if ($user?->trashed()) {
            $user->restore();
        }

        $isNew = $user === null;

        $user ??= new User([
            'name' => $googleUser->getName() ?: Str::before($email, '@'),
            'email' => $email,
            'password' => Str::password(32),
        ]);

        $user->forceFill([
            'google_id' => (string) $googleUser->getId(),
            'avatar' => $googleUser->getAvatar(),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        // Mismo evento que el registro con correo y contraseña (avisa al dueño de la plataforma).
        if ($isNew) {
            event(new Registered($user));
        }

        return $user;
    }
}
