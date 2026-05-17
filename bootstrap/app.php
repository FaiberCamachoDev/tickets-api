<?php

use App\Notifications\RateLimitExceededAlert;
use App\Notifications\ServerExceptionAlert;
use App\Traits\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->appendToGroup('api', \App\Http\Middleware\LogActivity::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        Integration::handles($exceptions);

        // Solo rutas /api/* reciben respuestas JSON
        $exceptions->shouldRenderJsonWhen(fn (Request $r) => $r->is('api/*'));

        // Validation (422)
        $exceptions->render(function (ValidationException $e, Request $r) {
            if ($r->is('api/*')) {
                return response()->json([
                    'status'  => 'error',
                    'message' => $e->getMessage(),
                    'errors'  => $e->errors(),
                ], 422);
            }
        });

        // Model not found (404)
        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $r) {
            if ($r->is('api/*')) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'El recurso solicitado no existe',
                ], 404);
            }
        });

        // Unauthenticated (401)
        $exceptions->render(function (AuthenticationException $e, Request $r) {
            if ($r->is('api/*')) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'No autenticado. Incluye el token en el header Authorization: Bearer <token>',
                ], 401);
            }
        });

        // Rate limit exceeded (429)
        $exceptions->render(function (TooManyRequestsHttpException $e, Request $r) {
            if ($r->is('api/*')) {
                rescue(fn () => Notification::route('discord', config('services.discord.webhook_url'))
                    ->notify(new RateLimitExceededAlert($e, $r)));

                return response()->json([
                    'status'  => 'error',
                    'message' => 'Demasiadas solicitudes. Espera un momento e intenta de nuevo.',
                    'retry_after' => $e->getHeaders()['Retry-After'] ?? null,
                ], 429);
            }
        });

        // Fallback genérico (500) — nunca exponer stacktrace al cliente
        $exceptions->render(function (\Throwable $e, Request $r) {
            if ($r->is('api/*')) {
                rescue(fn () => Notification::route('discord', config('services.discord.webhook_url'))
                    ->notify(new ServerExceptionAlert($e, $r)));

                return response()->json([
                    'status'  => 'error',
                    'message' => 'Error interno del servidor. El equipo ha sido notificado.',
                ], 500);
            }
        });

    })->create();
