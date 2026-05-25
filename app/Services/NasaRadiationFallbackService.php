<?php

namespace App\Services;

use App\Models\ApiWeatherData;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class NasaRadiationFallbackService
{
    /**
     * @param  array<int, string>  $timestamps
     * @param  array<string, float|null>  $allskyByTimestamp
     * @param  array<string, float|null>  $t2mByTimestamp
     * @param  array<string, float|null>  $rh2mByTimestamp
     * @param  array<string, float|null>  $precipByTimestamp
     * @param  array<string, float|null>  $windByTimestamp
     * @return array{value: float|null, source: string, method: string, confidence: float}
     */
    public function resolve(
        Carbon $dateTime,
        string $timestamp,
        array $timestamps,
        array $allskyByTimestamp,
        array $t2mByTimestamp = [],
        array $rh2mByTimestamp = [],
        array $precipByTimestamp = [],
        array $windByTimestamp = [],
        array $historicalMonthAverages = [],
    ): array {
        $current = $allskyByTimestamp[$timestamp] ?? null;

        if ($current !== null) {
            return [
                'value' => $current,
                'source' => 'nasa_real',
                'method' => 'nasa_real',
                'confidence' => 1.00,
            ];
        }

        $interpolated = $this->interpolateRecent($timestamp, $timestamps, $allskyByTimestamp);

        if ($interpolated !== null) {
            return [
                'value' => $interpolated,
                'source' => 'estimated',
                'method' => 'interpolated_recent',
                'confidence' => 0.85,
            ];
        }

        $weatherSignalsEstimate = $this->estimateFromWeatherSignals(
            $dateTime,
            $t2mByTimestamp[$timestamp] ?? null,
            $rh2mByTimestamp[$timestamp] ?? null,
            $precipByTimestamp[$timestamp] ?? null,
            $windByTimestamp[$timestamp] ?? null
        );
        if ($weatherSignalsEstimate !== null) {
            return [
                'value' => $weatherSignalsEstimate,
                'source' => 'estimated',
                'method' => 'weather_signals_model',
                'confidence' => 0.80,
            ];
        }

        $historicalMonthAverage = $this->historicalMonthAverage($dateTime, $historicalMonthAverages);
        if ($historicalMonthAverage !== null) {
            return [
                'value' => $this->adjustByWeatherSignals(
                    $historicalMonthAverage,
                    $t2mByTimestamp[$timestamp] ?? null,
                    $rh2mByTimestamp[$timestamp] ?? null,
                    $precipByTimestamp[$timestamp] ?? null,
                    $windByTimestamp[$timestamp] ?? null,
                    20.0
                ),
                'source' => 'estimated',
                'method' => 'historical_monthly',
                'confidence' => 0.72,
            ];
        }

        $climatology = $this->riohachaClimatology($dateTime);
        if ($climatology !== null) {
            return [
                'value' => $this->adjustByWeatherSignals(
                    $climatology,
                    $t2mByTimestamp[$timestamp] ?? null,
                    $rh2mByTimestamp[$timestamp] ?? null,
                    $precipByTimestamp[$timestamp] ?? null,
                    $windByTimestamp[$timestamp] ?? null,
                    20.0
                ),
                'source' => 'estimated',
                'method' => 'riohacha_climatology',
                'confidence' => 0.60,
            ];
        }

        $lastValid = $this->lastValidKnown($dateTime);
        if ($lastValid !== null) {
            return [
                'value' => $lastValid,
                'source' => 'estimated',
                'method' => 'last_valid_known',
                'confidence' => 0.45,
            ];
        }

        return [
            'value' => null,
            'source' => 'unavailable',
            'method' => 'unavailable',
            'confidence' => 0.00,
        ];
    }

    /**
     * @param  array<int, string>  $timestamps
     * @param  array<string, float|null>  $allskyByTimestamp
     */
    private function interpolateRecent(string $timestamp, array $timestamps, array $allskyByTimestamp): ?float
    {
        $index = array_search($timestamp, $timestamps, true);
        if ($index === false) {
            return null;
        }

        $previous = null;
        for ($i = $index - 1; $i >= 0; $i--) {
            $candidate = $allskyByTimestamp[$timestamps[$i]] ?? null;
            if ($candidate !== null) {
                $previous = ['index' => $i, 'value' => $candidate];
                break;
            }
        }

        $next = null;
        for ($i = $index + 1, $len = count($timestamps); $i < $len; $i++) {
            $candidate = $allskyByTimestamp[$timestamps[$i]] ?? null;
            if ($candidate !== null) {
                $next = ['index' => $i, 'value' => $candidate];
                break;
            }
        }

        if ($previous === null && $next === null) {
            return null;
        }

        if ($previous !== null && $next !== null) {
            $prevDistance = max(1, $index - (int) $previous['index']);
            $nextDistance = max(1, (int) $next['index'] - $index);
            $weightPrev = 1 / $prevDistance;
            $weightNext = 1 / $nextDistance;

            return ((float) $previous['value'] * $weightPrev + (float) $next['value'] * $weightNext) / ($weightPrev + $weightNext);
        }

        return (float) (($previous['value'] ?? $next['value']) ?? 0.0);
    }

    /**
     * @param  array<int, float>  $historicalMonthAverages
     */
    private function historicalMonthAverage(Carbon $dateTime, array $historicalMonthAverages = []): ?float
    {
        $month = $dateTime->month;

        if (array_key_exists($month, $historicalMonthAverages)) {
            return (float) $historicalMonthAverages[$month];
        }

        return ApiWeatherData::query()
            ->whereMonth('date_time', $month)
            ->whereNotNull('allsky_sfc_sw_dwn')
            ->avg('allsky_sfc_sw_dwn');
    }

    private function lastValidKnown(Carbon $dateTime): ?float
    {
        $row = ApiWeatherData::query()
            ->where('date_time', '<', $dateTime)
            ->whereNotNull('allsky_sfc_sw_dwn')
            ->orderByDesc('date_time')
            ->first(['allsky_sfc_sw_dwn']);

        return $row?->allsky_sfc_sw_dwn !== null ? (float) $row->allsky_sfc_sw_dwn : null;
    }

    private function riohachaClimatology(Carbon $dateTime): ?float
    {
        /** @var array<int, float> $monthlyHsp */
        $monthlyHsp = config('services.nasa_power.riohacha_monthly_hsp', []);
        $hsp = $monthlyHsp[$dateTime->month] ?? null;

        if (! is_numeric($hsp)) {
            return null;
        }

        return ((float) $hsp * 1000) / 24;
    }

    private function adjustByWeatherSignals(
        float $baseRadiationWm2,
        ?float $temperature,
        ?float $humidity,
        ?float $precipitation,
        ?float $windSpeed,
        float $minFloor = 20.0
    ): float {
        $factor = 1.0;

        if ($precipitation !== null && $precipitation > 0.5) {
            $factor -= min(0.25, $precipitation / 20);
        }

        if ($humidity !== null && $humidity > 80) {
            $factor -= min(0.12, ($humidity - 80) / 200);
        }

        if ($temperature !== null && $temperature >= 33) {
            $factor -= min(0.07, ($temperature - 33) / 100);
        }

        if ($windSpeed !== null && $windSpeed >= 7) {
            $factor += 0.02;
        }

        return max($minFloor, $baseRadiationWm2 * max(0.55, min(1.10, $factor)));
    }

    private function estimateFromWeatherSignals(
        Carbon $dateTime,
        ?float $temperature,
        ?float $humidity,
        ?float $precipitation,
        ?float $windSpeed
    ): ?float {
        $availableSignals = collect([$temperature, $humidity, $precipitation, $windSpeed])
            ->filter(fn ($value) => $value !== null)
            ->count();

        if ($availableSignals < 2) {
            return null;
        }

        $hour = ((int) $dateTime->hour) + 0.5;
        $daylightCurve = sin(pi() * (($hour - 6.0) / 12.0));
        if ($daylightCurve <= 0) {
            return 0.0;
        }

        $seasonalDailyMean = $this->riohachaClimatology($dateTime) ?? 220.0;
        $estimatedNoonPeak = $seasonalDailyMean * 3.5;
        $baseByHour = $estimatedNoonPeak * $daylightCurve;

        return $this->adjustByWeatherSignals(
            $baseByHour,
            $temperature,
            $humidity,
            $precipitation,
            $windSpeed,
            0.0
        );
    }
}
