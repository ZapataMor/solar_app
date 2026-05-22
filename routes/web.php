<?php

use App\Http\Controllers\SolarProjectController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::get('solar-projects', [SolarProjectController::class, 'index'])->name('solar-projects.index');
    Route::get('solar-projects/create', [SolarProjectController::class, 'create'])->name('solar-projects.create');
    Route::post('solar-projects', [SolarProjectController::class, 'store'])->name('solar-projects.store');
    Route::get('solar-projects/{solarProject}', [SolarProjectController::class, 'show'])->name('solar-projects.show');
    Route::get('solar-projects/{solarProject}/edit', [SolarProjectController::class, 'edit'])->name('solar-projects.edit');
    Route::put('solar-projects/{solarProject}', [SolarProjectController::class, 'update'])->name('solar-projects.update');
    Route::delete('solar-projects/{solarProject}', [SolarProjectController::class, 'destroy'])->name('solar-projects.destroy');
    Route::post('solar-projects/{solarProject}/fetch-weather-data', [SolarProjectController::class, 'fetchWeatherData'])
        ->name('solar-projects.fetch-weather-data');
});

require __DIR__.'/settings.php';
