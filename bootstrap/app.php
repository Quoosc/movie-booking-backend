<?php
// bootstrap/app.php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\JwtAuthMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // alias cho middleware JWT và RBAC
        $middleware->alias([
            'auth.jwt' => JwtAuthMiddleware::class,
            'role' => \App\Http\Middleware\CheckRole::class,
        ]);

        // Enable CORS globally
        $middleware->use([
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        // Remove any global auth middleware from api group
        $middleware->removeFromGroup('api', [\Illuminate\Auth\Middleware\Authenticate::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
