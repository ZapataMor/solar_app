<?php

use App\Http\Controllers\ApiDataController;
use App\Http\Controllers\SolarProjectController;
use App\Models\Municipality;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check()
    ? redirect()->route('solar-projects.index')
    : view('landing', [
        'municipalities' => Municipality::query()->active()->orderBy('name')->pluck('name'),
    ]))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('solar-projects', [SolarProjectController::class, 'index'])->name('solar-projects.index');
    Route::get('solar-projects/create', [SolarProjectController::class, 'create'])->name('solar-projects.create');
    Route::get('solar-projects/simulator/ambient-context', [SolarProjectController::class, 'ambientSimulatorContext'])
        ->name('solar-projects.simulator.ambient-context');
    Route::get('municipalities/{municipality}/solar-price', [SolarProjectController::class, 'solarPrice'])
        ->name('municipalities.solar-price');
    Route::post('solar-projects', [SolarProjectController::class, 'store'])->name('solar-projects.store');
    Route::post('solar-projects/recalculate-outdated', [SolarProjectController::class, 'recalculateOutdated'])
        ->name('solar-projects.recalculate-outdated');
    Route::post('solar-projects/{solarProject}/ai-recommendations', [SolarProjectController::class, 'aiRecommendations'])
        ->name('solar-projects.ai-recommendations');
    Route::post('solar-projects/{solarProject}/ai-prediction', [SolarProjectController::class, 'aiPrediction'])
        ->name('solar-projects.ai-prediction');
    Route::get('solar-projects/{solarProject}', [SolarProjectController::class, 'show'])->name('solar-projects.show');
    Route::get('solar-projects/{solarProject}/live-status', [SolarProjectController::class, 'liveStatus'])
        ->name('solar-projects.live-status');
    Route::get('solar-projects/{solarProject}/edit', [SolarProjectController::class, 'edit'])->name('solar-projects.edit');
    Route::put('solar-projects/{solarProject}', [SolarProjectController::class, 'update'])->name('solar-projects.update');
    Route::delete('solar-projects/{solarProject}', [SolarProjectController::class, 'destroy'])->name('solar-projects.destroy');
    Route::post('solar-projects/{solarProject}/calculate', [SolarProjectController::class, 'calculate'])
        ->name('solar-projects.calculate');
    Route::post('solar-projects/{solarProject}/calculate-weather-station', [SolarProjectController::class, 'calculateWithWeatherStation'])
        ->name('solar-projects.calculate-weather-station');
    Route::post('solar-projects/{solarProject}/calculate-nasa', [SolarProjectController::class, 'calculateWithNasaPower'])
        ->name('solar-projects.calculate-nasa');
    Route::post('solar-projects/{solarProject}/calculate-ambient-weather', [SolarProjectController::class, 'calculateWithAmbientWeather'])
        ->name('solar-projects.calculate-ambient-weather');

    Route::view('guia-recibo', 'guides.energy-bill')->name('guides.energy-bill');
    Route::view('instaladores', 'installers.index')->name('installers.index');

    Route::get('api-data', ApiDataController::class)
        ->middleware('can:administer-platform')
        ->name('api-data.index');

    // Climate readings are global: only system administrators may sync them.
    Route::middleware('can:sync-climate-data')->group(function () {
        Route::post('solar-projects/{solarProject}/fetch-weather-data', [SolarProjectController::class, 'fetchWeatherData'])
            ->name('solar-projects.fetch-weather-data');
        Route::post('solar-projects/{solarProject}/fetch-weather-station-data', [SolarProjectController::class, 'fetchWeatherStationData'])
            ->name('solar-projects.fetch-weather-station-data');
        Route::post('api-data/fetch-nasa-data', [ApiDataController::class, 'fetchNasaData'])
            ->name('api-data.fetch-nasa-data');
        Route::post('api-data/fetch-weather-station-data', [ApiDataController::class, 'fetchWeatherStationData'])
            ->name('api-data.fetch-weather-station-data');
        Route::post('api-data/fetch-ambient-data', [ApiDataController::class, 'fetchAmbientData'])
            ->name('api-data.fetch-ambient-data');
    });
});

require __DIR__.'/settings.php';
