<?php

use App\Exceptions\BusinessException;
use App\Http\Middleware\EnsureActiveShift;
use App\Http\Middleware\EnsureUserActive;
use App\Http\Middleware\ForceJson;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [ForceJson::class]);
        $middleware->alias([
            'shift' => EnsureActiveShift::class,
            'active' => EnsureUserActive::class,
        ]);
        // API token (Bearer) — tidak memakai cookie/session stateful.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $isApi = fn (Request $r) => $r->is('api/*') || $r->expectsJson();

        $exceptions->render(function (BusinessException $e, Request $r) {
            return ApiResponse::error($e->getMessage(), $e->status, $e->errors);
        });
        $exceptions->render(function (ValidationException $e, Request $r) use ($isApi) {
            if ($isApi($r)) {
                return ApiResponse::error($e->validator->errors()->first() ?: 'Data yang dikirim tidak valid.', 422, $e->errors());
            }
        });
        $exceptions->render(function (AuthenticationException $e, Request $r) use ($isApi) {
            if ($isApi($r)) {
                return ApiResponse::error('Sesi Anda telah berakhir. Silakan login kembali.', 401);
            }
        });
        $exceptions->render(function (AuthorizationException|AccessDeniedHttpException $e, Request $r) use ($isApi) {
            if ($isApi($r)) {
                return ApiResponse::error('Anda tidak memiliki akses untuk tindakan ini.', 403);
            }
        });
        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $r) use ($isApi) {
            if ($isApi($r)) {
                return ApiResponse::error('Data tidak ditemukan.', 404);
            }
        });
        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $r) use ($isApi) {
            if ($isApi($r)) {
                return ApiResponse::error('Metode tidak diizinkan.', 405);
            }
        });
        $exceptions->render(function (ThrottleRequestsException $e, Request $r) use ($isApi) {
            if ($isApi($r)) {
                $retry = $e->getHeaders()['Retry-After'] ?? 60;

                return ApiResponse::error("Terlalu banyak permintaan. Coba lagi dalam {$retry} detik.", 429)->withHeaders($e->getHeaders());
            }
        });
        $exceptions->render(function (HttpException $e, Request $r) use ($isApi) {
            if ($isApi($r)) {
                return ApiResponse::error($e->getMessage() ?: 'Terjadi kesalahan.', $e->getStatusCode());
            }
        });
        $exceptions->render(function (\Throwable $e, Request $r) use ($isApi) {
            if ($isApi($r) && ! config('app.debug')) {
                report($e);

                return ApiResponse::error('Terjadi kesalahan pada server. Silakan coba lagi.', 500);
            }
        });
    })->create();
