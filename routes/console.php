<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// timezone() goes before between(): between() reads the timezone when it is called, so the
// other order evaluates the window in UTC (01:00–13:30 in Colombia) and skips the afternoon.
Schedule::command('weather-station:fetch')
    ->timezone(config('services.weather_station.schedule_timezone', 'America/Bogota'))
    ->everyFiveMinutes()
    ->between('06:00', '18:30')
    ->withoutOverlapping();

// NASA publishes daily data once a day; every 6 h re-checks the window and confirms estimates (ADR-0009).
Schedule::command('nasa-power:fetch')
    ->everySixHours()
    ->timezone(config('app.display_timezone', 'America/Bogota'))
    ->withoutOverlapping();

// Ambient Weather — every 5 minutes, all day: the station also reports at night ("Ahora mismo" shows
// it) and each run fills the gap since the last stored reading (1–2 requests).
// withoutOverlapping() prevents pile-up when the API is slow.
// onOneServer() is a no-op on single-server deployments but safe to include.
Schedule::command('ambient:sync')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();
