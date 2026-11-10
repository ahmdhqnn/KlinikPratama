<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            $status = $response->getStatusCode();
            if ($status < 400 || $status >= 600 || $request->is('api/*') || $request->expectsJson()
                || str_starts_with($response->headers->get('Content-Type', ''), 'application/json')) {
                return $response;
            }

            $inertia = (bool) $request->header('X-Inertia');
            $workflowMessage = in_array($status, [409, 422], true) && $exception instanceof HttpExceptionInterface
                ? trim($exception->getMessage())
                : '';

            if ($inertia && ! $request->isMethodSafe() && in_array($status, [409, 419, 422], true)) {
                $error = response()->json(['workflowError' => $workflowMessage ?: match ($status) {
                    409 => 'Data sudah berubah. Tinjau data terbaru sebelum melanjutkan.',
                    419 => 'Sesi telah berakhir. Muat ulang halaman untuk melanjutkan.',
                    default => 'Periksa kembali isian dan tahapan layanan.',
                }], $status);
            } else {
                Inertia::flushShared();

                $retryAfter = $response->headers->get('Retry-After', '');
                $error = Inertia::render('error', [
                    'status' => $status,
                    'message' => $workflowMessage ?: null,
                    'canRetry' => $request->isMethod('GET') && in_array($status, [419, 500, 502, 503, 504], true),
                    'retryAfter' => ctype_digit($retryAfter) ? (int) $retryAfter : null,
                    'returnToLogin' => $request->is('login') || $status === 401,
                ])->toResponse($request)->setStatusCode($status);
            }

            foreach ($response->headers->all() as $name => $values) {
                if (in_array($name, ['allow', 'retry-after', 'www-authenticate'], true) || str_starts_with($name, 'x-ratelimit-')) {
                    $error->headers->set($name, $values);
                }
            }

            $error->setVary([...$response->getVary(), 'Accept', 'X-Inertia']);
            $error->headers->set('Cache-Control', 'private, no-store');

            return $error;
        });
    })->create();
