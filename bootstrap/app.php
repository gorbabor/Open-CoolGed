<?php

use App\Http\Middleware\EnsureTenantActive;
use App\Http\Middleware\SetTenantContext;
use App\Http\Middleware\SuperAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Both run as ROUTE middleware, after 'auth', so the tenant context
        // is resolved from the authenticated user before any model lookup.
        // (Route model binding must not depend on the tenant context.)
        $middleware->alias([
            'tenant.context' => SetTenantContext::class,
            'tenant.active' => EnsureTenantActive::class,
            'superadmin' => SuperAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
