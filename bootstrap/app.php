<?php

use App\Http\Middleware\UpdateUserTimezone;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Add timezone middleware to web group
        $middleware->web(append: [
            // apply middleware globally to web routes
            UpdateUserTimezone::class,

            // specified middleware for web group
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
