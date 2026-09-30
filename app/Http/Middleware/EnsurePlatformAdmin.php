<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Panel de la plataforma: solo para los correos de PLATFORM_ADMIN_EMAILS.
 */
class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->is_platform_admin, 403);

        return $next($request);
    }
}
