<?php

namespace App\Http\Middleware;

use App\Actions\Climate\SyncDueSources;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Any visit to the app keeps the climate data up to date (ADR-0025).
 *
 * The work happens in terminate(), after the response has left, so nobody waits for it. Until this
 * existed, the only thing syncing production was somebody keeping *Datos climáticos* open: that
 * page polls every five minutes from the browser.
 */
class SyncClimateInBackground
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! config('services.climate_sync.on_traffic', true)) {
            return;
        }

        // A page someone is reading. Not the sync endpoints themselves, which are already doing it,
        // and not the JSON the climate page polls for.
        if (! $request->isMethod('GET') || $request->expectsJson() || $request->routeIs('api-data.*')) {
            return;
        }

        try {
            app(SyncDueSources::class)();
        } catch (Throwable $exception) {
            // The visitor already has their page: a sync that cannot start must never surface here.
            report($exception);
        }
    }
}
