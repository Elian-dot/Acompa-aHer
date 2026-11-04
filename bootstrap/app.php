<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetTeamUrlDefaults;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SetTeamUrlDefaults::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $exception, Request $request): ?JsonResponse {
            return $request->is('api/v1/*')
                ? response()->json(['message' => 'Revisa los campos indicados.', 'errors' => $exception->errors()], $exception->status)
                : null;
        });
        $exceptions->render(function (AuthenticationException $exception, Request $request): ?JsonResponse {
            return $request->is('api/v1/*')
                ? response()->json(['message' => 'Inicia sesión para continuar.'], 401)
                : null;
        });
        $exceptions->render(function (HttpExceptionInterface $exception, Request $request): ?JsonResponse {
            if (! $request->is('api/v1/*')) {
                return null;
            }
            $message = match ($exception->getStatusCode()) {
                403 => 'Tu cuenta no tiene permiso para realizar esta acción.',
                404 => 'El recurso solicitado no está disponible.',
                405 => 'Esta operación no está disponible.',
                409 => 'Otra persona ya actualizó el recurso. Actualiza la página.',
                419 => 'Tu sesión caducó. Recarga la página y vuelve a intentarlo.',
                429 => 'Demasiadas solicitudes. Espera un momento y vuelve a intentarlo.',
                503 => 'El servicio no está disponible en este momento. Inténtalo de nuevo.',
                default => 'No se pudo completar la solicitud.',
            };

            return response()->json(['message' => $message], $exception->getStatusCode(), $exception->getHeaders());
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
