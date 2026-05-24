<?php

namespace App\Services;

use App\Models\ApiWeatherData;
use App\Models\SolarProject;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class NasaWeatherDataService
{
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
            ->unique()
            ->sort()
            ->values();

        if ($timestamps->isEmpty()) {
            throw new \RuntimeException('NASA POWER response does not contain temporal values.');
        }

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($parameters, $timestamps, &$created, &$updated): void {
            foreach ($timestamps as $timestamp) {
                $dateTime = $this->parseNasaTimestamp($timestamp);

                $allsky = $this->cleanValue($parameters['ALLSKY_SFC_SW_DWN'][$timestamp] ?? null);
                $t2m = $this->cleanValue($parameters['T2M'][$timestamp] ?? null);
                $rh2m = $this->cleanValue($parameters['RH2M'][$timestamp] ?? null);
                $prectotcorr = $this->cleanValue($parameters['PRECTOTCORR'][$timestamp] ?? null);
                $ws10m = $this->cleanValue($parameters['WS10M'][$timestamp] ?? null);

                if ($allsky === null && $t2m === null && $rh2m === null && $prectotcorr === null && $ws10m === null) {
                    continue;
                }

                $weatherData = ApiWeatherData::query()->updateOrCreate(
                    ['date_time' => $dateTime],
                    [
                        'allsky_sfc_sw_dwn' => $allsky,
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
