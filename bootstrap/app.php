<?php

use App\Http\Middleware\CheckMaintenanceMode;
use App\Http\Middleware\CheckRememberedDevice;
use App\Http\Middleware\CheckUserBan;
use App\Http\Middleware\ProgressiveLockout;
use App\Http\Middleware\UpdateUserTimezone;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Exceptions\Renderer\Renderer;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            // Streaming is intentionally outside the web middleware group: a
            // long-running video response must not hold the session lock.
            require base_path('routes/stream.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Specific middleware with alias
        $middleware->alias([
            'check.banned' => CheckUserBan::class,
            'progressive.lockout' => ProgressiveLockout::class,
        ]);

        // Apply to web group globally
        $middleware->web(append: [
            CheckMaintenanceMode::class,
            UpdateUserTimezone::class,
            CheckUserBan::class,
            CheckRememberedDevice::class,
            ProgressiveLockout::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Render Bleep's own error pages everywhere except local debugging,
        // where Laravel's detailed exception page is more useful. JSON clients
        // keep receiving the framework's JSON error payloads. Framework
        // exceptions that carry their own response or redirect are untouched.
        $exceptions->render(function (Throwable $exception, Request $request) {
            if ($request->expectsJson()
                || $exception instanceof HttpResponseException
                || $exception instanceof ValidationException
                || $exception instanceof AuthenticationException) {
                return null;
            }

            $status = match (true) {
                $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
                $exception instanceof ModelNotFoundException => 404,
                $exception instanceof AuthorizationException => 403,
                default => 500,
            };

            if ($status < 400) {
                return null;
            }

            // Laravel renders HTTP exception views (errors/404, errors/500…)
            // even in debug mode, so the debug page has to be built explicitly
            // instead of returning null and letting the framework pick a view.
            if (config('app.debug') && app()->environment('local') && app()->bound(Renderer::class)) {
                return response(
                    app(Renderer::class)->render($request, $exception),
                    $status,
                    $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [],
                );
            }

            $view = view()->exists('errors.'.$status) ? 'errors.'.$status : 'errors.generic';

            return response()->view($view, [
                'exception' => $exception,
                'status' => $status,
            ], $status);
        });
    })->create();
