<?php

use App\Http\Middleware\EnsureCurrentAccount;
use App\Http\Middleware\EnsurePlatformAdmin;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'account' => EnsureCurrentAccount::class,
            'platform' => EnsurePlatformAdmin::class,
        ]);

        // El negocio se fija antes de evaluar permisos (can:...).
        $middleware->prependToPriorityList(Authorize::class, EnsureCurrentAccount::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
