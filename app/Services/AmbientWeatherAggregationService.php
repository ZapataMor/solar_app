<?php

namespace App\Services;

use App\Models\AmbientWeatherReading;
use App\Models\SolarProject;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Queries and aggregates AmbientWeatherReading records.
 *
 * Mirrors WeatherStationAggregationService in purpose and interface so that
 * ProjectDashboardService can use both interchangeably.
 */
class AmbientWeatherAggregationService
{
    /**
     * Return all Ambient readings that fall within the project's date range.
     *
     * @return Collection<int, AmbientWeatherReading>
     */
    public function readingsForProject(SolarProject $solarProject): Collection
    {
        return AmbientWeatherReading::query()
            ->whereBetween('recorded_at', [
                $solarProject->start_date->copy()->startOfDay(),
                $solarProject->end_date->copy()->endOfDay(),
            ])
            ->orderBy('recorded_at')
            ->get();
    }

    /**
     * Return the N most recent readings across all stations (no project filter).
     *
     * Used by the dashboard's "recent readings" panel.
     *
     * @return Collection<int, AmbientWeatherReading>
     */
    public function latestReadings(int $limit = 60): Collection
    {
        return AmbientWeatherReading::query()
            ->orderByDesc('recorded_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Return the single most recent reading, or null when no data exists.
     */
    public function latestReading(): ?AmbientWeatherReading
    {
        return AmbientWeatherReading::query()
            ->orderByDesc('recorded_at')
            ->first();
    }

    /**
     * Collapse a collection of readings into one row per calendar day.
     *
     * Output format is compatible with ApiWeatherData rows consumed by
     * DashboardTimeScaleService and ProjectDashboardService::buildFuturePredictions().
     *
     * Unit notes:
     *   allsky_sfc_sw_dwn → averaged solar_radiation (W/m²)
     *   t2m               → averaged temperature (°C)
     *   rh2m              → averaged humidity (%)
     *   prectotcorr       → summed rainfall (mm/day) — Ambient provides hourly, sum → daily
     *   ws10m             → averaged wind_speed converted to m/s (÷ 3.6)
     *
     * @param  Collection<int, AmbientWeatherReading>  $readings
     * @return Collection<int, array{date_time: Carbon, allsky_sfc_sw_dwn: float, t2m: float|null, rh2m: float|null, prectotcorr: float|null, ws10m: float|null}>
     */
    public function dailyRows(Collection $readings): Collection
    {
        return $readings
            ->groupBy(fn (AmbientWeatherReading $r) => $r->recorded_at->toDateString())
            ->map(function (Collection $dayReadings) {
                $radiation = $dayReadings
                    ->map(fn (AmbientWeatherReading $r) => $r->radiationValue())
                    ->filter(fn (?float $v) => $v !== null)
                    ->average();

                if ($radiation === null) {
                    return null;
                }

                $avgWindKmh = $dayReadings->average(
                    fn (AmbientWeatherReading $r) => $r->wind_speed !== null ? (float) $r->wind_speed : null
                );

                return [
                    'date_time'        => Carbon::parse($dayReadings->first()->recorded_at)->startOfDay(),
                    'allsky_sfc_sw_dwn'=> (float) $radiation,
                    't2m'              => $dayReadings->average(
                        fn (AmbientWeatherReading $r) => $r->temperature !== null ? (float) $r->temperature : null
                    ),
                    'rh2m'             => $dayReadings->average(
                        fn (AmbientWeatherReading $r) => $r->humidity !== null ? (float) $r->humidity : null
                    ),
                    'prectotcorr'      => $dayReadings->sum(
                        fn (AmbientWeatherReading $r) => $r->rainfall !== null ? (float) $r->rainfall : 0.0
                    ) ?: null,
                    // Convert km/h → m/s for NASA-compatible units
                    'ws10m'            => $avgWindKmh !== null ? round($avgWindKmh / 3.6, 3) : null,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * Compute summary statistics for a collection of Ambient readings.
     *
     * Mirrors WeatherStationAggregationService::stats() for dashboard parity.
     *
     * @param  Collection<int, AmbientWeatherReading>  $readings
     * @return array{
     *     total: int,
     *     averageRadiation: float|null,
     *     maxRadiation: float|null,
     *     averageTemperature: float|null,
     *     averageHumidity: float|null,
     *     maxUvIndex: float|null,
     *     latest: AmbientWeatherReading|null
     * }
     */
    public function stats(Collection $readings): array
    {
        $radiationValues = $readings
            ->map(fn (AmbientWeatherReading $r) => $r->radiationValue())
            ->filter(fn (?float $v) => $v !== null)
            ->values();

        return [
            'total'              => $readings->count(),
            'averageRadiation'   => $radiationValues->average(),
            'maxRadiation'       => $radiationValues->max(),
            'averageTemperature' => $readings->avg(fn (AmbientWeatherReading $r) => $r->temperature !== null ? (float) $r->temperature : null),
            'averageHumidity'    => $readings->avg(fn (AmbientWeatherReading $r) => $r->humidity !== null ? (float) $r->humidity : null),
            'maxUvIndex'         => $readings->max('uv_index'),
            'latest'             => $readings->sortByDesc('recorded_at')->first(),
        ];
    }

    /**
     * Build chart-ready arrays from a collection of readings.
     *
     * @param  Collection<int, AmbientWeatherReading>  $readings
     * @return array{labels: array<int, string>, radiation: array<int, float>}
     */
    public function chartData(Collection $readings): array
    {
        $dailyRadiation = $readings
            ->groupBy(fn (AmbientWeatherReading $r) => $r->recorded_at->toDateString())
            ->map(fn (Collection $dayReadings) => $dayReadings
                ->map(fn (AmbientWeatherReading $r) => $r->radiationValue())
                ->filter(fn (?float $v) => $v !== null)
                ->average())
            ->filter(fn (?float $v) => $v !== null);

        return [
            'labels'    => $dailyRadiation->keys()->values()->all(),
            'radiation' => $dailyRadiation->map(fn ($v) => (float) $v)->values()->all(),
        ];
    }
}
