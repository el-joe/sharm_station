<?php

use App\Exceptions\DomainValidationException;
use App\Http\Middleware\EnsureStaffRole;
use App\Http\Middleware\EnsureTwoFactorEnrolled;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('web')
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The Next.js server proxies auth calls and forwards X-Forwarded-For: per-IP rate limits use the real client IP.
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);
        $middleware->alias(['staff' => EnsureStaffRole::class, 'two-factor' => EnsureTwoFactorEnrolled::class]);
        $middleware->redirectGuestsTo(fn () => route('admin.login'));
        $middleware->redirectUsersTo(fn () => route('admin.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // API errors are always {message, errors?}; without APP_DEBUG nothing internal (trace, file, line, class) leaks.
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*') || config('app.debug')
                || $exception instanceof ValidationException || $exception instanceof AuthenticationException
                || $exception instanceof DomainValidationException) {
                return null;
            }

            $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
            $message = match (true) {
                $exception instanceof ModelNotFoundException => 'Not found.',
                $exception instanceof HttpExceptionInterface && $exception->getMessage() !== '' => $exception->getMessage(),
                default => SymfonyResponse::$statusTexts[$status] ?? 'Server Error',
            };

            return response()->json(['message' => $status >= 500 ? 'Server Error' : $message], $status, $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : []);
        });
    })->create();
