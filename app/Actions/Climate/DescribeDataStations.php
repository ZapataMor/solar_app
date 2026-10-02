<?php

namespace App\Actions\Climate;

use App\Domain\Climate\Wind;
use App\Models\AmbientWeatherReading;

/**
 * Use case: the live numbers of the 3D stations on the climate data page (ADR-0018). The vane and
 * cups of the Ambient Weather station follow the wind of its latest reading, and the satellite of
 * NASA POWER measures the point the app asks NASA for. The stations themselves are in the view.
 */
final class DescribeDataStations
{
    /**
     * @return array{wind: array{speedKmh: float|null, directionDegrees: int|null, text: string}, nasaPoint: array{latitude: float, longitude: float}}
     */
    public function __invoke(): array
    {
        return [
            'wind' => $this->wind(),
            'nasaPoint' => [
                'latitude' => (float) config('services.nasa_power.latitude'),
                'longitude' => (float) config('services.nasa_power.longitude'),
            ],
        ];
    }

    /**
     * The wind of the latest Ambient Weather reading that has one. The sync sends it again, so the
     * vane follows the station without reloading the page.
     *
     * @return array{speedKmh: float|null, directionDegrees: int|null, text: string}
     */
    public function wind(): array
    {
        $reading = AmbientWeatherReading::query()
            ->whereNotNull('wind_speed')
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->first(['wind_speed', 'wind_direction']);

        if ($reading === null) {
            return ['speedKmh' => null, 'directionDegrees' => null, 'text' => 'sin lecturas todavía'];
        }

        $wind = new Wind((float) $reading->wind_speed, $reading->wind_direction);

        return [
            'speedKmh' => round($wind->speedKmh, 1),
            'directionDegrees' => $wind->directionDegrees,
            'text' => $wind->describe(),
        ];
    }
}
