<?php

namespace App\Services;

use App\Models\ApiWeatherData;
use App\Models\SolarProject;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class NasaWeatherDataService
{
    public function __construct(
        private readonly NasaRadiationFallbackService $radiationFallbackService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{created: int, updated: int}
     */
    public function storeDailyData(array $payload): array
    {
        $parameters = data_get($payload, 'properties.parameter', []);
        if (! is_array($parameters) || $parameters === []) {
            throw new \RuntimeException('NASA POWER response does not include parameter data.');
        }

        $timestamps = collect($parameters)
            ->flatMap(fn (array $values) => array_keys($values))
            ->map(fn ($timestamp) => (string) $timestamp)
            ->unique()
            ->sort()
            ->values();

        if ($timestamps->isEmpty()) {
            throw new \RuntimeException('NASA POWER response does not contain temporal values.');
        }

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($parameters, $timestamps, &$created, &$updated): void {
            $allskyByTimestamp = collect($timestamps)->mapWithKeys(fn ($timestamp) => [
                $timestamp => $this->cleanValue($parameters['ALLSKY_SFC_SW_DWN'][$timestamp] ?? null),
            ])->all();
            $t2mByTimestamp = collect($timestamps)->mapWithKeys(fn ($timestamp) => [
                $timestamp => $this->cleanValue($parameters['T2M'][$timestamp] ?? null),
            ])->all();
            $rh2mByTimestamp = collect($timestamps)->mapWithKeys(fn ($timestamp) => [
                $timestamp => $this->cleanValue($parameters['RH2M'][$timestamp] ?? null),
            ])->all();
            $prectotcorrByTimestamp = collect($timestamps)->mapWithKeys(fn ($timestamp) => [
                $timestamp => $this->cleanValue($parameters['PRECTOTCORR'][$timestamp] ?? null),
            ])->all();
            $ws10mByTimestamp = collect($timestamps)->mapWithKeys(fn ($timestamp) => [
                $timestamp => $this->cleanValue($parameters['WS10M'][$timestamp] ?? null),
            ])->all();

            foreach ($timestamps as $timestamp) {
                $dateTime = $this->parseNasaTimestamp($timestamp);
                $radiation = $this->radiationFallbackService->resolve(
                    $dateTime,
                    $timestamp,
                    $timestamps->all(),
                    $allskyByTimestamp,
                    $t2mByTimestamp,
                    $rh2mByTimestamp,
                    $prectotcorrByTimestamp,
                    $ws10mByTimestamp,
                );

                $allsky = $radiation['value'];
                $t2m = $t2mByTimestamp[$timestamp] ?? null;
                $rh2m = $rh2mByTimestamp[$timestamp] ?? null;
                $prectotcorr = $prectotcorrByTimestamp[$timestamp] ?? null;
                $ws10m = $ws10mByTimestamp[$timestamp] ?? null;

                if ($allsky === null && $t2m === null && $rh2m === null && $prectotcorr === null && $ws10m === null) {
                    continue;
                }

                if ($radiation['method'] !== 'nasa_real') {
                    Log::warning('NASA radiation fallback applied', [
                        'timestamp' => $timestamp,
                        'method' => $radiation['method'],
                        'confidence' => $radiation['confidence'],
                        'value' => $allsky,
                    ]);
                }

                $weatherData = ApiWeatherData::query()->updateOrCreate(
                    ['date_time' => $dateTime],
                    [
                        'allsky_sfc_sw_dwn' => $allsky,
                        'radiation_source' => (string) $radiation['source'],
                        'radiation_fallback_method' => (string) $radiation['method'],
                        'radiation_confidence' => (float) $radiation['confidence'],
                        't2m' => $t2m,
                        'rh2m' => $rh2m,
                        'prectotcorr' => $prectotcorr,
                        'ws10m' => $ws10m,
                    ],
                );

                $weatherData->wasRecentlyCreated ? $created++ : $updated++;
            }
        });

        return [
            'created' => $created,
            'updated' => $updated,
        ];
    }

    public function dataForProject(SolarProject $solarProject): \Illuminate\Support\Collection
    {
        return ApiWeatherData::query()
            ->whereBetween('date_time', [
                $solarProject->start_date->copy()->startOfDay(),
                $solarProject->end_date->copy()->endOfDay(),
            ])
            ->orderBy('date_time')
            ->get();
    }

    public function countForProject(SolarProject $solarProject): int
    {
        return ApiWeatherData::query()
            ->whereBetween('date_time', [
                $solarProject->start_date->copy()->startOfDay(),
                $solarProject->end_date->copy()->endOfDay(),
            ])
            ->count();
    }

    private function cleanValue(mixed $value): ?float
    {
        if ($value === null || (float) $value <= -900) {
            return null;
        }

        return (float) $value;
    }

    private function parseNasaTimestamp(mixed $timestamp): Carbon
    {
        $timestamp = (string) $timestamp;

        if (preg_match('/^\d{8}$/', $timestamp)) {
            return Carbon::createFromFormat('Ymd', $timestamp)->startOfDay();
        }

        if (preg_match('/^\d{10}$/', $timestamp)) {
            return Carbon::createFromFormat('YmdH', $timestamp)->startOfHour();
        }

        throw new \RuntimeException("NASA POWER returned an invalid timestamp [{$timestamp}].");
    }
}
