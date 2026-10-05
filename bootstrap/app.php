<?php

use App\Http\Middleware\SyncClimateInBackground;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Any visit keeps the climate data up to date, after the response has left (ADR-0025).
        $middleware->web(append: [SyncClimateInBackground::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
