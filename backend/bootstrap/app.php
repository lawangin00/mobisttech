<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\IdentityAuthenticated;
use App\Http\Middleware\IdentityContext;
use App\Http\Middleware\IdentityCsrf;
use App\Http\Middleware\RecentlyAuthenticated;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\RecordNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            require __DIR__.'/../routes/identity.php';
            require __DIR__.'/../routes/website-payment-admin.php';
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [HandleInertiaRequests::class]);
        $middleware->group('identity', [
            IdentityContext::class,
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            IdentityCsrf::class,
            SubstituteBindings::class,
        ]);
        $middleware->alias(['identity.auth' => IdentityAuthenticated::class, 'identity.recent' => RecentlyAuthenticated::class]);
        $middleware->prependToPriorityList(EncryptCookies::class, IdentityContext::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $error, Request $request) {
            if (! $request->attributes->has('identity_realm')) {
                return null;
            }
            $businessApi = $request->is('api/v1/*') && ! $request->is('api/v1/auth/*') && ! $request->is('api/v1/account');
            $status = $error instanceof ValidationException ? 422
                : ($error instanceof TokenMismatchException ? 419
                    : (($businessApi && ($error instanceof ModelNotFoundException || $error instanceof RecordNotFoundException)) ? 404
                        : (($businessApi && $error instanceof LogicException) ? 409
                            : ($error instanceof HttpExceptionInterface ? $error->getStatusCode() : 500))));
            $prefix = $businessApi ? 'api_' : 'identity_';
            $body = ['code' => $prefix.$status, 'message' => $status === 500 ? ($businessApi ? 'API service unavailable.' : 'Identity service unavailable.') : 'The request could not be completed.', 'request_id' => (string) Str::uuid()];
            if ($error instanceof ValidationException) {
                $body['fields'] = $error->errors();
            }

            return response()->json(['error' => $body], $status, $error instanceof HttpExceptionInterface ? $error->getHeaders() : [])
                ->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
        });
        $exceptions->render(function (Throwable $error, Request $request) {
            if (! $request->is('api/v1/*') || $request->attributes->has('identity_realm')) {
                return null;
            }
            $status = $error instanceof ValidationException ? 422
                : (($error instanceof ModelNotFoundException || $error instanceof RecordNotFoundException) ? 404
                    : ($error instanceof HttpExceptionInterface ? $error->getStatusCode()
                        : ($error instanceof LogicException ? 409 : 500)));
            $body = ['code' => 'api_'.$status, 'message' => $status === 500 ? 'API service unavailable.' : 'The request could not be completed.',
                'request_id' => (string) Str::uuid()];
            if ($error instanceof ValidationException) {
                $body['fields'] = $error->errors();
            }

            return response()->json(['error' => $body], $status, $error instanceof HttpExceptionInterface ? $error->getHeaders() : [])
                ->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
