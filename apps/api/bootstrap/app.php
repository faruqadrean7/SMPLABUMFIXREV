<?php

use App\Http\Middleware\BypassThrottle;
use App\Http\Middleware\RequirePermission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '*'));
        $middleware->redirectGuestsTo(fn () => null);
        $aliases = [
            'permission' => RequirePermission::class,
        ];
        if (env('TEST_DISABLE_RATE_LIMIT', false) || env('DISABLE_RATE_LIMITING', false)) {
            $aliases['throttle'] = BypassThrottle::class;
        }
        $middleware->alias($aliases);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $renderError = function (string $message, string $code, int $status, array $details = [], array $headers = []) {
            $requestId = request()->header('X-Request-Id', (string) Str::uuid());

            return response()->json([
                'error' => [
                    'code' => $code,
                    'message' => $message,
                    'details' => $details,
                    'requestId' => $requestId,
                ],
            ], $status, array_merge($headers, ['X-Request-Id' => $requestId]));
        };

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($renderError) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return $renderError('Sesi tidak valid atau telah berakhir', 'UNAUTHENTICATED', 401);
            }
        });

        // H-01 fix: Unify all FormRequest validation failures to the canonical
        // 400 VALIDATION_FAILED contract. BaseApiRequest already throws
        // HttpResponseException before this handler runs, so there is no
        // double-conversion. Only raw FormRequest subclasses reach here.
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $details = [];
                $errors = $e->errors();
                foreach ($errors as $field => $messages) {
                    foreach ($messages as $message) {
                        $details[] = ['field' => $field, 'message' => $message];
                    }
                }

                $requestId = request()->header('X-Request-Id', (string) Str::uuid());

                return response()->json([
                    'error' => [
                        'code' => 'VALIDATION_FAILED',
                        'message' => 'Data tidak valid',
                        'details' => $details,
                        'requestId' => $requestId,
                    ],
                    'message' => 'Data tidak valid',
                    'errors' => $errors,
                ], 422, ['X-Request-Id' => $requestId]);
            }
        });

        $exceptions->render(function (AuthorizationException|AccessDeniedHttpException $e, Request $request) use ($renderError) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return $renderError('Anda tidak memiliki izin untuk tindakan ini', 'FORBIDDEN', 403);
            }
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $request) use ($renderError) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return $renderError('Data tidak ditemukan', 'NOT_FOUND', 404);
            }
        });

        $exceptions->render(function (UniqueConstraintViolationException $e, Request $request) use ($renderError) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return $renderError('Data duplikat atau terjadi konflik data', 'CONFLICT', 409);
            }
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) use ($renderError) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return $renderError('Terlalu banyak permintaan, coba lagi nanti', 'RATE_LIMITED', 429);
            }
        });

        // BLCK-03: Invalid signed URL request handling
        $exceptions->render(function (InvalidSignatureException $e, Request $request) use ($renderError) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return $renderError('Tautan tidak valid atau tanda tangan telah kedaluwarsa', 'FORBIDDEN', 403);
            }
        });

        // BLCK-03: General HttpExceptionInterface handler for status semantics (403, 404, 405, etc.)
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($renderError) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $status = $e->getStatusCode();
                $code = $e->getHeaders()['X-Error-Code'] ?? match ($status) {
                    400 => 'BAD_REQUEST',
                    401 => 'UNAUTHENTICATED',
                    403 => 'FORBIDDEN',
                    404 => 'NOT_FOUND',
                    405 => 'METHOD_NOT_ALLOWED',
                    409 => 'CONFLICT',
                    422 => 'UNPROCESSABLE_ENTITY',
                    429 => 'RATE_LIMITED',
                    default => $status >= 500 ? 'INTERNAL_SERVER_ERROR' : 'HTTP_ERROR',
                };

                $message = $status >= 500 && ! config('app.debug')
                    ? 'Terjadi kesalahan internal server'
                    : ($e->getMessage() ?: match ($status) {
                        400 => 'Permintaan tidak valid',
                        401 => 'Sesi tidak valid atau telah berakhir',
                        403 => 'Anda tidak memiliki izin untuk tindakan ini',
                        404 => 'Data tidak ditemukan',
                        405 => 'Metode HTTP tidak diizinkan untuk rute ini',
                        409 => 'Data duplikat atau terjadi konflik data',
                        422 => 'Data tidak dapat diproses',
                        429 => 'Terlalu banyak permintaan, coba lagi nanti',
                        default => 'Terjadi kesalahan permintaan',
                    });

                return $renderError($message, $code, $status, [], $e->getHeaders());
            }
        });

        $exceptions->render(function (Throwable $e, Request $request) use ($renderError) {
            if (($request->is('api/*') || $request->expectsJson()) && ! config('app.debug')) {
                return $renderError('Terjadi kesalahan internal server', 'INTERNAL_SERVER_ERROR', 500);
            }
        });
    })->create();
