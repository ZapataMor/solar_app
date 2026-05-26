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
     * Radiation methodology (Ambient Weather vs NASA POWER):
     *   NASA POWER stores allsky_sfc_sw_dwn as the true 24-hour average W/m²
     *   (day + night). The SolarCalculationService converts it to HSP via × 24/1000.
     *
     *   Ambient sensors report instantaneous W/m² every ~5 min during daylight only.
     *   Simply averaging those daytime readings and multiplying by 24 overestimates
     *   HSP by a factor of ~2-3× because nighttime irradiance (= 0) is excluded.
     *
     *   Fix: trapezoidal integration over actual measurement intervals gives total
     *   daily energy (kWh/m²/day = HSP), which is then divided by 24 to produce a
     *   true 24h-average W/m² compatible with the existing calculation engine.
     *
     * Temperature derating:
     *   Since Ambient provides direct ambient temperature, we apply the standard
     *   panel power temperature coefficient (−0.40 %/°C above 25 °C STC) to the
     *   effective irradiance. This improves accuracy over NASA POWER (which only
     *   has satellite-derived air temperature) and makes Ambient the highest-quality
     *   source for solar energy calculations.
     *
     * Unit notes:
     *   allsky_sfc_sw_dwn → true 24h-avg W/m² (temperature-derated)
     *   t2m               → averaged temperature (°C)
     *   rh2m              → averaged humidity (%)
     *   prectotcorr       → summed rainfall (mm/day)
     *   ws10m             → averaged wind_speed converted km/h → m/s
     *   daily_hsp_kwh     → pre-derating HSP (kWh/m²/day) — informational
     *   temp_correction   → derating factor applied (e.g. 0.94 for 40 °C avg)
     *   radiation_source  → always 'ambient_sensor'
     *
     * @param  Collection<int, AmbientWeatherReading>  $readings
     * @return Collection<int, array{date_time: Carbon, allsky_sfc_sw_dwn: float, t2m: float|null, rh2m: float|null, prectotcorr: float|null, ws10m: float|null, daily_hsp_kwh: float, temp_correction: float, radiation_source: string}>
     */
    public function dailyRows(Collection $readings): Collection
    {
        return $readings
            ->groupBy(fn (AmbientWeatherReading $r) => $r->recorded_at->toDateString())
            ->map(function (Collection $dayReadings) {
                // Only use readings that have valid radiation values
                $sorted = $dayReadings
                    ->filter(fn (AmbientWeatherReading $r) => $r->radiationValue() !== null)
                    ->sortBy('recorded_at')
                    ->values();

                if ($sorted->isEmpty()) {
                    return null;
                }

                // Trapezoidal integration: Σ [(G_i + G_{i+1})/2 × Δt_hours]
                // Gaps > 30 min (sensor offline) are capped so an outage doesn't
                // add phantom energy to the integral.
                $dailyHsp = $this->trapezoidalHsp($sorted, 30);

                // Convert kWh/m²/day → 24h-average W/m² (NASA-compatible format).
                // SolarCalculationService will apply × 24/1000 to recover HSP.
                $allsky24hAvg = $dailyHsp * 1000.0 / 24.0;

                // Temperature derating: standard monocrystalline Si coefficient
                $avgTemp = $dayReadings->average(
                    fn (AmbientWeatherReading $r) => $r->temperature !== null ? (float) $r->temperature : null
                );
                $tempCorrection = $this->temperatureCorrection($avgTemp);

                $avgWindKmh = $dayReadings->average(
                    fn (AmbientWeatherReading $r) => $r->wind_speed !== null ? (float) $r->wind_speed : null
                );

                return [
                    'date_time'         => Carbon::parse($sorted->first()->recorded_at)->startOfDay(),
                    'allsky_sfc_sw_dwn' => round($allsky24hAvg * $tempCorrection, 4),
                    't2m'               => $avgTemp,
                    'rh2m'              => $dayReadings->average(
                        fn (AmbientWeatherReading $r) => $r->humidity !== null ? (float) $r->humidity : null
                    ),
                    'prectotcorr'       => ($rain = $dayReadings->sum(
                        fn (AmbientWeatherReading $r) => $r->rainfall !== null ? (float) $r->rainfall : 0.0
                    )) > 0.0 ? $rain : null,
                    'ws10m'             => $avgWindKmh !== null ? round($avgWindKmh / 3.6, 3) : null,
                    // Metadata — ignored by weatherDataFromRows() but useful for debugging
                    'daily_hsp_kwh'     => round($dailyHsp, 4),
                    'temp_correction'   => $tempCorrection,
                    'radiation_source'  => 'ambient_sensor',
                ];
            })
            ->filter()
            ->values();
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Trapezoidal integration of solar radiation readings.
     *
     * Returns daily HSP in kWh/m²/day.
     * Nighttime irradiance is 0 W/m² and produces no readings, so it
     * contributes nothing to the sum — which is correct.
     *
     * @param  Collection<int, AmbientWeatherReading>  $sorted  Readings sorted by recorded_at, non-null radiation only
     * @param  int  $maxGapMinutes  Cap individual intervals to avoid inflating energy during outages
     */
    private function trapezoidalHsp(Collection $sorted, int $maxGapMinutes = 30): float
    {
        $maxGapH = $maxGapMinutes / 60.0;

        if ($sorted->count() === 1) {
            // Only one reading: assume a single 5-minute measurement window
            return (float) $sorted->first()->radiationValue() * (5.0 / 60.0) / 1000.0;
        }

        $totalWh = 0.0;
        $n = $sorted->count();

        for ($i = 1; $i < $n; $i++) {
            $prev = $sorted[$i - 1];
            $curr = $sorted[$i];
            $dtH  = min($prev->recorded_at->diffInSeconds($curr->recorded_at) / 3600.0, $maxGapH);
            $avgG = ((float) $prev->radiationValue() + (float) $curr->radiationValue()) / 2.0;
            $totalWh += $avgG * $dtH;
        }

        // Trailing half-interval for the last reading (same width as the preceding gap)
        $dtLastH = min(
            $sorted[$n - 2]->recorded_at->diffInSeconds($sorted[$n - 1]->recorded_at) / 3600.0,
            $maxGapH
        );
        $totalWh += (float) $sorted[$n - 1]->radiationValue() * $dtLastH;

        return $totalWh / 1000.0; // Wh/m² → kWh/m²/day
    }

    /**
     * Standard monocrystalline-silicon panel power temperature coefficient.
     *
     * −0.40 %/°C above 25 °C (STC). Result is clamped to [0.50, 1.00].
     */
    private function temperatureCorrection(?float $avgTempC): float
    {
        if ($avgTempC === null || $avgTempC <= 25.0) {
            return 1.0;
        }

        return max(0.5, 1.0 - 0.004 * ($avgTempC - 25.0));
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
