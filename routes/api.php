<?php

use App\Http\Controllers\Api\WeatherStationReadingController;
use Illuminate\Support\Facades\Route;

Route::match(['get', 'post'], 'weather-station/readings', [WeatherStationReadingController::class, 'store'])
    ->name('api.weather-station.readings.store');
