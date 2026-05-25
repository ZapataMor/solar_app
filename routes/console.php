<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('weather-station:fetch')
    ->everyFiveMinutes()
    ->between('06:00', '18:30')
    ->timezone(config('services.weather_station.schedule_timezone', 'America/Bogota'))
    ->withoutOverlapping();

Schedule::command('nasa-power:fetch')
    ->hourlyAt(1)
    ->timezone(config('app.timezone', 'America/Bogota'))
    ->withoutOverlapping();
