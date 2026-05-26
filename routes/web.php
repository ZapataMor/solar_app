<?php

use App\Http\Controllers\ApiDataController;
use App\Http\Controllers\SolarProjectController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('solar-projects', [SolarProjectController::class, 'index'])->name('solar-projects.index');
    Route::get('solar-projects/create', [SolarProjectController::class, 'create'])->name('solar-projects.create');
    Route::post('solar-projects', [SolarProjectController::class, 'store'])->name('solar-projects.store');
    Route::get('solar-projects/{solarProject}', [SolarProjectController::class, 'show'])->name('solar-projects.show');
    Route::get('solar-projects/{solarProject}/edit', [SolarProjectController::class, 'edit'])->name('solar-projects.edit');
    Route::put('solar-projects/{solarProject}', [SolarProjectController::class, 'update'])->name('solar-projects.update');
    Route::delete('solar-projects/{solarProject}', [SolarProjectController::class, 'destroy'])->name('solar-projects.destroy');
    Route::post('solar-projects/{solarProject}/calculate', [SolarProjectController::class, 'calculate'])
        ->name('solar-projects.calculate');
    Route::post('solar-projects/{solarProject}/calculate-weather-station', [SolarProjectController::class, 'calculateWithWeatherStation'])
        ->name('solar-projects.calculate-weather-station');
    Route::post('solar-projects/{solarProject}/fetch-weather-data', [SolarProjectController::class, 'fetchWeatherData'])
        ->name('solar-projects.fetch-weather-data');
    Route::post('solar-projects/{solarProject}/fetch-weather-station-data', [SolarProjectController::class, 'fetchWeatherStationData'])
        ->name('solar-projects.fetch-weather-station-data');
    Route::post('solar-projects/{solarProject}/calculate-ambient-weather', [SolarProjectController::class, 'calculateWithAmbientWeather'])
        ->name('solar-projects.calculate-ambient-weather');

    Route::get('api-data', ApiDataController::class)->name('api-data.index');
    Route::post('api-data/fetch-nasa-data', [ApiDataController::class, 'fetchNasaData'])
        ->name('api-data.fetch-nasa-data');
    Route::post('api-data/fetch-weather-station-data', [ApiDataController::class, 'fetchWeatherStationData'])
        ->name('api-data.fetch-weather-station-data');
    Route::post('api-data/fetch-ambient-data', [ApiDataController::class, 'fetchAmbientData'])
        ->name('api-data.fetch-ambient-data');
});

require __DIR__.'/settings.php';
