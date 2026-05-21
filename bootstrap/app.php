<?php

use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Run CORS globally so OPTIONS preflights get headers even on 404 routes.
        $middleware->prepend(\Illuminate\Http\Middleware\HandleCors::class);

        // Force JSON responses on all /api/* routes so validation errors and
        // auth failures return JSON instead of web-style redirects.
        $middleware->api(prepend: [ForceJsonResponse::class]);

        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Return 401 JSON instead of redirecting to /login for unauthenticated API requests.
        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['error' => 'Unauthenticated.'], 401);
            }
        });
    })->create();
