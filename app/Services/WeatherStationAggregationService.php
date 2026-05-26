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
     * Collapse a collection of readings into one row per calendar day.
     *
     * Radiation methodology:
     *   The weather station reports instantaneous W/m² at irregular intervals.
     *   A simple average of active readings overestimates HSP when the SolarCalculation-
     *   Service multiplies by × 24/1000 (designed for NASA POWER 24h-averages).
     *
     *   Fix: trapezoidal integration over actual measurement timestamps gives total
     *   energy (kWh/m²/day = HSP), divided by 24 to get a 24h-average W/m² that is
     *   directly compatible with the existing calculation engine.
     *
     *   Gaps > 60 min (station offline / batch endpoint) are capped so an outage
     *   does not artificially inflate the energy integral.
     *
     * Temperature derating:
     *   When temperature data is available, the standard −0.40 %/°C panel coefficient
     *   is applied above 25 °C STC, improving accuracy over raw irradiance figures.
     *
     * @return Collection<int, array{date_time: Carbon, allsky_sfc_sw_dwn: float, t2m: float|null, rh2m: float|null, prectotcorr: null, ws10m: null, daily_hsp_kwh: float, temp_correction: float, radiation_source: string}>
     */
    public function dailyRows(Collection $readings): Collection
    {
        return $readings
            ->groupBy(fn (WeatherStationReading $reading) => $reading->measured_at->toDateString())
            ->map(function (Collection $dayReadings) {
                $sorted = $dayReadings
                    ->filter(fn (WeatherStationReading $r) => $r->radiationValue() !== null)
                    ->sortBy('measured_at')
                    ->values();

                if ($sorted->isEmpty()) {
                    return null;
                }

                // Trapezoidal integration; max gap 60 min (station sends data in batches)
                $dailyHsp = $this->trapezoidalHsp($sorted, 60);

                // 24h-average W/m² — compatible with SolarCalculationService (× 24/1000 = HSP)
                $allsky24hAvg = $dailyHsp * 1000.0 / 24.0;

                $avgTemp = $dayReadings->average(
                    fn (WeatherStationReading $r) => $r->temperature !== null ? (float) $r->temperature : null
                );
                $tempCorrection = $this->temperatureCorrection($avgTemp);

                return [
                    'date_time'         => Carbon::parse($sorted->first()->measured_at)->startOfDay(),
                    'allsky_sfc_sw_dwn' => round($allsky24hAvg * $tempCorrection, 4),
                    't2m'               => $avgTemp,
                    'rh2m'              => $dayReadings->average(
                        fn (WeatherStationReading $r) => $r->humidity !== null ? (float) $r->humidity : null
                    ),
                    'prectotcorr'       => null,
                    'ws10m'             => null,
                    'daily_hsp_kwh'     => round($dailyHsp, 4),
                    'temp_correction'   => $tempCorrection,
                    'radiation_source'  => 'weather_station',
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
     *
     * @param  Collection<int, WeatherStationReading>  $sorted  Sorted by measured_at, non-null radiation only
     * @param  int  $maxGapMinutes  Cap per-interval width to avoid inflating energy during outages
     */
    private function trapezoidalHsp(Collection $sorted, int $maxGapMinutes = 60): float
    {
        $maxGapH = $maxGapMinutes / 60.0;

        if ($sorted->count() === 1) {
            // Single reading: assume a 15-minute representative window
            return (float) $sorted->first()->radiationValue() * (15.0 / 60.0) / 1000.0;
        }

        $totalWh = 0.0;
        $n = $sorted->count();

        for ($i = 1; $i < $n; $i++) {
            $prev = $sorted[$i - 1];
            $curr = $sorted[$i];
            $dtH  = min($prev->measured_at->diffInSeconds($curr->measured_at) / 3600.0, $maxGapH);
            $avgG = ((float) $prev->radiationValue() + (float) $curr->radiationValue()) / 2.0;
            $totalWh += $avgG * $dtH;
        }

        // Trailing half-interval for the last reading
        $dtLastH = min(
            $sorted[$n - 2]->measured_at->diffInSeconds($sorted[$n - 1]->measured_at) / 3600.0,
            $maxGapH
        );
        $totalWh += (float) $sorted[$n - 1]->radiationValue() * $dtLastH;

        return $totalWh / 1000.0;
    }

    /**
     * Standard monocrystalline-silicon panel power temperature coefficient.
     * −0.40 %/°C above 25 °C (STC). Clamped to [0.50, 1.00].
     */
    private function temperatureCorrection(?float $avgTempC): float
    {
        if ($avgTempC === null || $avgTempC <= 25.0) {
            return 1.0;
        }

        return max(0.5, 1.0 - 0.004 * ($avgTempC - 25.0));
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
