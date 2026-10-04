<?php

/*
|--------------------------------------------------------------------------
| Cuentas (negocios que usan el sistema)
|--------------------------------------------------------------------------
*/

return [
    // Días de prueba de un negocio recién registrado.
    'trial_days' => (int) env('ACCOUNT_TRIAL_DAYS', 30),

    // Correos de los administradores de la plataforma (Kipusoft). Se sincroniza
    // users.is_platform_admin en cada inicio de sesión: quitar un correo de
    // aquí le quita el acceso al panel de la plataforma.
    'platform_admins' => array_values(array_filter(array_map(
        fn (string $email) => strtolower(trim($email)),
        explode(',', (string) env('PLATFORM_ADMIN_EMAILS', '')),
    ))),

    // A quién se avisa por correo cuando alguien crea su cuenta por primera
    // vez (separados por coma). Sin valor, se avisa a los administradores de
    // la plataforma.
    'signup_notification_emails' => array_values(array_filter(array_map(
        fn (string $email) => strtolower(trim($email)),
        explode(',', (string) (env('SIGNUP_NOTIFICATION_EMAIL') ?: env('PLATFORM_ADMIN_EMAILS', ''))),
    ))),
];
