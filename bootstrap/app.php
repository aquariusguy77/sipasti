<?php

use App\Services\AuditLogger;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Percobaan akses di luar kewenangan peran dicatat pada log audit (NFR2, NFR3).
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if ($user = $request->user()) {
                app(AuditLogger::class)->write(
                    $user->auditActor(),
                    'access.denied',
                    'Route',
                    $request->route()?->getName(),
                    $request->method().' '.$request->path(),
                );
            }

            return null;
        });
    })->create();
