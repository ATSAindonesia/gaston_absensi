<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\RedirectAdminsToAdminArea;
use App\Http\Middleware\UseAdminLayout;
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
        // Percayai header X-Forwarded-* dari tunnel (ngrok) agar skema https
        // terbaca benar saat diakses dari HP. Hanya untuk dev via tunnel.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'admin.area' => RedirectAdminsToAdminArea::class,
            'admin.layout' => UseAdminLayout::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
