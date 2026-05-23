<?php

namespace App\Services;

use App\Models\SolarProject;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class NasaWeatherDataService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{created: int, updated: int}
     */
    public function storeDailyData(SolarProject $solarProject, array $payload): array
    {
        $parameters = data_get($payload, 'properties.parameter', []);

        $timestamps = collect($parameters)
            ->flatMap(fn (array $values) => array_keys($values))
            ->unique()
            ->sort()
            ->values();

        if ($timestamps->isEmpty()) {
            throw new \RuntimeException('NASA POWER response does not contain daily values.');
        }

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($solarProject, $parameters, $timestamps, &$created, &$updated): void {
            $solarProject->weatherData()
                ->get()
                ->filter(fn ($weatherData) => ! $weatherData->date_time->isStartOfDay())
                ->each->delete();

            foreach ($timestamps as $timestamp) {
                $weatherData = $solarProject->weatherData()->updateOrCreate(
                    ['date_time' => Carbon::createFromFormat('Ymd', (string) $timestamp)->startOfDay()],
                    [
                        'allsky_sfc_sw_dwn' => $this->cleanValue($parameters['ALLSKY_SFC_SW_DWN'][$timestamp] ?? null),
                        't2m' => $this->cleanValue($parameters['T2M'][$timestamp] ?? null),
                        'rh2m' => $this->cleanValue($parameters['RH2M'][$timestamp] ?? null),
                        'prectotcorr' => $this->cleanValue($parameters['PRECTOTCORR'][$timestamp] ?? null),
                        'ws10m' => $this->cleanValue($parameters['WS10M'][$timestamp] ?? null),
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

    private function cleanValue(mixed $value): ?float
    {
        if ($value === null || (float) $value <= -900) {
            return null;
        }

        return (float) $value;
    }
}
