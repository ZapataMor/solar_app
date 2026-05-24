<?php

namespace App\Services;

use App\Models\WeatherStationReading;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class WeatherStationImportService
{
    /**
     * @return array{received: int, created: int, updated: int, skipped: int}
     */
    public function importAll(): array
    {
        $endpoint = config('services.weather_station.endpoint');
        $deviceCode = config('services.weather_station.device_code', 'METEOESTACION');
        $since = $this->latestImportedAt($deviceCode);
        $received = 0;
        $created = 0;
        $updated = 0;
        $skipped = 0;

        if (! $endpoint) {
            throw new RuntimeException('Weather station endpoint is not configured.');
        }

        $response = $this->fetchEndpoint($endpoint, $this->endpointQuery($since));

        if (! $response->successful()) {
            $bodyPreview = Str::of((string) $response->body())->squish()->limit(180);
            throw new RuntimeException("Weather station API request failed with status {$response->status()}. Body: {$bodyPreview}");
        }

        $payload = $response->json();
        $rows = $this->extractRows($payload);

        if ($rows === null) {
            throw new RuntimeException('Weather station API returned an invalid payload structure.');
        }

        $received = count($rows);

        foreach ($rows as $row) {
            if (! is_array($row) || blank($row['fecha'] ?? null)) {
                continue;
            }

            try {
                $measuredAt = Carbon::parse($row['fecha']);
            } catch (Throwable) {
                $skipped++;

                continue;
            }

            if ($since && $measuredAt->lessThanOrEqualTo($since)) {
                $skipped++;

                continue;
            }

            $exists = WeatherStationReading::query()
                ->where('device_code', $deviceCode)
                ->where('measured_at', $measuredAt)
                ->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            WeatherStationReading::query()->create([
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

    /**
     * @param  array<string, string>  $query
     */
    private function fetchEndpoint(string $endpoint, array $query)
    {
        $request = Http::timeout(20)
            ->acceptJson()
            ->asJson()
            ->withHeaders($this->requestHeaders())
            ->when(
                ! config('services.weather_station.verify_ssl', true),
                fn ($request) => $request->withoutVerifying(),
            );

        $response = $request->get($endpoint, $query);
        if ($response->status() !== 403) {
            return $response;
        }

        // Some WAF setups block JSON signatures from server-side clients.
        $fallbackHeaders = array_merge($this->requestHeaders(), [
            'Accept' => '*/*',
            'Cache-Control' => 'no-cache',
            'Pragma' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);

        $fallbackResponse = Http::timeout(20)
            ->withHeaders($fallbackHeaders)
            ->when(
                ! config('services.weather_station.verify_ssl', true),
                fn ($request) => $request->withoutVerifying(),
            )
            ->get($endpoint, $query);

        if ($fallbackResponse->status() !== 403) {
            return $fallbackResponse;
        }

        if (str_starts_with($endpoint, 'https://')) {
            $httpEndpoint = preg_replace('/^https:\/\//', 'http://', $endpoint) ?? $endpoint;
            $httpResponse = Http::timeout(20)
                ->withHeaders($fallbackHeaders)
                ->get($httpEndpoint, $query);

            if ($httpResponse->status() !== 403) {
                return $httpResponse;
            }
        }

        return $fallbackResponse;
    }

    private function latestImportedAt(string $deviceCode): ?CarbonInterface
    {
        $query = WeatherStationReading::query()
            ->where('device_code', $deviceCode);

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

    /**
     * @return array<string, string>
     */
    private function requestHeaders(): array
    {
        $headers = [
            'User-Agent' => (string) config('services.weather_station.user_agent', 'SolarApp/1.0'),
            'Accept' => 'application/json, text/plain, */*',
            'Accept-Language' => 'es-CO,es;q=0.9,en;q=0.8',
        ];

        $referer = config('services.weather_station.referer');
        if (is_string($referer) && $referer !== '') {
            $headers['Referer'] = $referer;
        }

        $apiKey = config('services.weather_station.api_key');
        $apiKeyHeader = config('services.weather_station.api_key_header', 'X-API-KEY');
        if (is_string($apiKey) && $apiKey !== '' && is_string($apiKeyHeader) && $apiKeyHeader !== '') {
            $headers[$apiKeyHeader] = $apiKey;
        }

        return $headers;
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function extractRows(mixed $payload): ?array
    {
        if (! is_array($payload)) {
            return null;
        }

        if ($this->isListOfRows($payload)) {
            return $payload;
        }

        $status = $payload['status'] ?? $payload['estado'] ?? null;
        $isSuccess = $status === null
            || $status === 'success'
            || $status === 'ok'
            || $status === true
            || $status === 1;

        if (! $isSuccess) {
            return null;
        }

        foreach (['datos', 'data', 'readings', 'registros', 'results'] as $key) {
            $candidate = $payload[$key] ?? null;
            if (is_array($candidate) && $this->isListOfRows($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<int|string, mixed>  $value
     */
    private function isListOfRows(array $value): bool
    {
        if (! array_is_list($value)) {
            return false;
        }

        if ($value === []) {
            return true;
        }

        return is_array($value[0] ?? null);
    }
}
