<?php

namespace App\Services;

use App\Models\SolarProject;
use App\Models\WeatherStationReading;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WeatherStationImportService
{
    /**
     * @return array{received: int, created: int, updated: int, skipped: int}
     */
    public function importAll(?SolarProject $solarProject = null): array
    {
        $endpoint = config('services.weather_station.endpoint');
        $deviceCode = config('services.weather_station.device_code', 'METEOESTACION');
        $since = $this->latestImportedAt($deviceCode, $solarProject);
        $received = 0;
        $created = 0;
        $updated = 0;
        $skipped = 0;

        if (! $endpoint) {
            throw new RuntimeException('Weather station endpoint is not configured.');
        }

        $request = Http::timeout(20)
            ->acceptJson()
            ->when(
                ! config('services.weather_station.verify_ssl', true),
                fn ($request) => $request->withoutVerifying(),
            );

        $payload = $request
            ->get($endpoint, $this->endpointQuery($since))
            ->throw()
            ->json();

        if (($payload['status'] ?? null) !== 'success' || ! is_array($payload['datos'] ?? null)) {
            throw new RuntimeException('Weather station API returned an invalid payload.');
        }

        $received = count($payload['datos']);

        foreach ($payload['datos'] as $row) {
            if (! is_array($row) || blank($row['fecha'] ?? null)) {
                continue;
            }

            $measuredAt = Carbon::parse($row['fecha']);

            if ($since && $measuredAt->lessThanOrEqualTo($since)) {
                $skipped++;

                continue;
            }

            $exists = WeatherStationReading::query()
                ->where('solar_project_id', $solarProject?->id)
                ->where('device_code', $deviceCode)
                ->where('measured_at', $measuredAt)
                ->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            WeatherStationReading::query()->create([
                'solar_project_id' => $solarProject?->id,
                'device_code' => $deviceCode,
                'temperature' => $this->numericValue($row['temperatura'] ?? null),
                'humidity' => $this->numericValue($row['humedad'] ?? null),
                'thermal_sensation' => $this->numericValue($row['sensacion_termica'] ?? null),
                'co2' => $this->integerValue($row['co2'] ?? null),
                'pm25' => $this->numericValue($row['pm25'] ?? null),
                'pm10' => $this->numericValue($row['pm10'] ?? null),
                'uva' => $this->numericValue($row['uva'] ?? null),
                'uvb' => $this->numericValue($row['uvb'] ?? null),
                'uv_index' => $this->numericValue($row['indice_uv'] ?? null),
                'solar_radiation' => $this->numericValue($row['radiacion'] ?? $row['radiacion_solar'] ?? $row['solar_radiation'] ?? null),
                'raw_payload' => $row,
                'measured_at' => $measuredAt,
            ]);

            $created++;
        }

        return [
            'received' => $received,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }

    private function latestImportedAt(string $deviceCode, ?SolarProject $solarProject): ?CarbonInterface
    {
        $query = WeatherStationReading::query()
            ->where('device_code', $deviceCode);

        if ($solarProject) {
            $query->where('solar_project_id', $solarProject->id);
        }

        $latest = $query->max('measured_at');

        return $latest ? Carbon::parse($latest) : null;
    }

    /**
     * @return array<string, string>
     */
    private function endpointQuery(?CarbonInterface $since): array
    {
        if (! $since) {
            return [];
        }

        return [
            config('services.weather_station.since_parameter', 'fecha_desde') => $since->format('Y-m-d H:i:s'),
        ];
    }

    private function numericValue(mixed $value): ?float
    {
        return $value !== null && $value !== '' ? (float) $value : null;
    }

    private function integerValue(mixed $value): ?int
    {
        return $value !== null && $value !== '' ? (int) $value : null;
    }
}
