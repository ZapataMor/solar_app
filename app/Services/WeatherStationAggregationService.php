<?php

namespace App\Services;

use App\Models\SolarProject;
use App\Models\WeatherStationReading;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class WeatherStationAggregationService
{
    /**
     * @return Collection<int, WeatherStationReading>
     */
    public function readingsForProject(SolarProject $solarProject): Collection
    {
        return WeatherStationReading::query()
            ->whereBetween('measured_at', [
                $solarProject->start_date->copy()->startOfDay(),
                $solarProject->end_date->copy()->endOfDay(),
            ])
            ->orderBy('measured_at')
            ->get();
    }

    /**
     * @return Collection<int, array{date_time: Carbon, allsky_sfc_sw_dwn: float, t2m: float|null, rh2m: float|null, prectotcorr: null, ws10m: null}>
     */
    public function dailyRows(Collection $readings): Collection
    {
        return $readings
            ->groupBy(fn (WeatherStationReading $reading) => $reading->measured_at->toDateString())
            ->map(function (Collection $dayReadings) {
                $radiation = $dayReadings
                    ->map(fn (WeatherStationReading $reading) => $reading->radiationValue())
                    ->filter(fn (?float $value) => $value !== null)
                    ->average();

                if ($radiation === null) {
                    return null;
                }

                return [
                    'date_time' => Carbon::parse($dayReadings->first()->measured_at)->startOfDay(),
                    'allsky_sfc_sw_dwn' => (float) $radiation,
                    't2m' => $dayReadings->average(fn (WeatherStationReading $reading) => $reading->temperature !== null ? (float) $reading->temperature : null),
                    'rh2m' => $dayReadings->average(fn (WeatherStationReading $reading) => $reading->humidity !== null ? (float) $reading->humidity : null),
                    'prectotcorr' => null,
                    'ws10m' => null,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @param  Collection<int, WeatherStationReading>  $readings
     * @return array{
     *     total: int,
     *     averageRadiation: float|null,
     *     maxRadiation: float|null,
     *     averageUva: float|null,
     *     averageUvb: float|null,
     *     maxUvIndex: float|null,
     *     latest: WeatherStationReading|null
     * }
     */
    public function stats(Collection $readings): array
    {
        $radiationValues = $readings
            ->map(fn (WeatherStationReading $reading) => $reading->radiationValue())
            ->filter(fn (?float $value) => $value !== null)
            ->values();

        return [
            'total' => $readings->count(),
            'averageRadiation' => $radiationValues->average(),
            'maxRadiation' => $radiationValues->max(),
            'averageUva' => $readings->avg('uva'),
            'averageUvb' => $readings->avg('uvb'),
            'maxUvIndex' => $readings->max('uv_index'),
            'latest' => $readings->sortByDesc('measured_at')->first(),
        ];
    }

    /**
     * @param  Collection<int, WeatherStationReading>  $readings
     * @return array{labels: array<int, string>, radiation: array<int, float>}
     */
    public function chartData(Collection $readings): array
    {
        $dailyRadiation = $readings
            ->groupBy(fn (WeatherStationReading $reading) => $reading->measured_at->toDateString())
            ->map(fn (Collection $dayReadings) => $dayReadings
                ->map(fn (WeatherStationReading $reading) => $reading->radiationValue())
                ->filter(fn (?float $value) => $value !== null)
                ->average())
            ->filter(fn (?float $value) => $value !== null);

        return [
            'labels' => $dailyRadiation->keys()->values()->all(),
            'radiation' => $dailyRadiation->map(fn ($value) => (float) $value)->values()->all(),
        ];
    }
}
