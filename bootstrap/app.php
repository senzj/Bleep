<?php

use App\Http\Middleware\CheckUserBan;
use Illuminate\Foundation\Application;
use App\Http\Middleware\UpdateUserTimezone;
use App\Http\Middleware\CheckRememberedDevice;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Specific middleware with alias
        $middleware->alias([
            'check.banned' => CheckUserBan::class,
        ]);

        // Apply to web group globally
        $middleware->web(append: [
            UpdateUserTimezone::class,
            CheckUserBan::class,
            CheckRememberedDevice::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
