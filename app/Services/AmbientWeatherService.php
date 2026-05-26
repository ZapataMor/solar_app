<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Low-level client for the Ambient Weather REST API v1.
 *
 * Responsibilities:
 *  - Authentication via apiKey + applicationKey query params
 *  - HTTP error handling with graceful fallback (never throws on rate-limit)
 *  - Short-lived response caching to respect API rate limits
 *  - Unit conversion: imperial → SI / project-internal units
 *  - Null-safe field parsing (missing fields become null, never exceptions)
 *
 * @see https://ambientweather.docs.apiary.io/
 */
class AmbientWeatherService
{
    private const RATE_LIMIT_STATUS = 429;

    /** Ambient returns this sentinel when a sensor reading is invalid. */
    private const AMBIENT_INVALID_SENTINEL = -9999;

    public function __construct()
    {
        // Intentionally empty — all config is read lazily so the service
        // can be resolved from the container even when Ambient is disabled.
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Return all devices registered to the account.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getDevices(): Collection
    {
        if (! $this->isEnabled()) {
            return collect();
        }

        $cacheKey = 'ambient_devices_' . md5((string) config('ambient.api_key'));
        $ttl = $this->cacheTtl();

        return Cache::remember($cacheKey, $ttl, function (): Collection {
            $response = Http::timeout($this->timeout())
                ->retry(2, 1000, fn (Throwable $e) => ! $this->isRateLimitException($e))
                ->get($this->endpoint('/devices'), $this->buildQueryParams());

            if ($response->status() === self::RATE_LIMIT_STATUS) {
                Log::warning('Ambient Weather API rate limit hit on getDevices().');

                return collect();
            }

            $response->throw();

            $payload = $response->json();

            if (! is_array($payload)) {
                throw new RuntimeException('Ambient Weather API returned an invalid device list payload.');
            }

            return collect($payload);
        });
    }

    /**
     * Return the most recent reading for a single station.
     *
     * @return array<string, mixed>|null
     */
    public function getLatestData(string $macAddress): ?array
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $cacheKey = 'ambient_latest_' . $macAddress;
        $ttl = $this->cacheTtl();

        return Cache::remember($cacheKey, $ttl, function () use ($macAddress): ?array {
            $response = Http::timeout($this->timeout())
                ->retry(2, 1000, fn (Throwable $e) => ! $this->isRateLimitException($e))
                ->get(
                    $this->endpoint("/devices/{$macAddress}"),
                    $this->buildQueryParams(['limit' => 1])
                );

            if ($response->status() === self::RATE_LIMIT_STATUS) {
                Log::warning('Ambient Weather API rate limit hit on getLatestData().', [
                    'mac_address' => $macAddress,
                ]);

                return null;
            }

            $response->throw();

            $payload = $response->json();

            if (! is_array($payload) || $payload === []) {
                return null;
            }

            // API returns an array; we want only the most recent entry.
            $reading = $payload[0] ?? null;

            return is_array($reading) ? $this->normalizeReading($reading) : null;
        });
    }

    /**
     * Return historical readings for a station.
     *
     * The Ambient Weather API paginates backwards from $end in blocks of up
     * to 288 records (1 per 5-minute interval for 24 hours).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getHistoricalData(
        string $macAddress,
        ?CarbonInterface $start = null,
        ?CarbonInterface $end = null,
        int $limit = 288,
    ): Collection {
        if (! $this->isEnabled()) {
            return collect();
        }

        $end ??= Carbon::now('UTC');
        $endEpochMs = $end->getTimestampMs();

        $extra = ['limit' => min(max(1, $limit), 288), 'endDate' => $endEpochMs];

        $response = Http::timeout($this->timeout())
            ->retry(2, 1000, fn (Throwable $e) => ! $this->isRateLimitException($e))
            ->get(
                $this->endpoint("/devices/{$macAddress}"),
                $this->buildQueryParams($extra)
            );

        if ($response->status() === self::RATE_LIMIT_STATUS) {
            Log::warning('Ambient Weather API rate limit hit on getHistoricalData().', [
                'mac_address' => $macAddress,
            ]);

            return collect();
        }

        $response->throw();

        $payload = $response->json();

        if (! is_array($payload)) {
            return collect();
        }

        $readings = collect($payload)
            ->filter(fn (mixed $item) => is_array($item))
            ->map(fn (array $item) => $this->normalizeReading($item));

        // Apply $start filter client-side (the API does not support startDate)
        if ($start !== null) {
            $readings = $readings->filter(
                fn (array $reading) => isset($reading['recorded_at'])
                    && Carbon::parse($reading['recorded_at'])->greaterThanOrEqualTo($start)
            );
        }

        return $readings->values();
    }

    // -------------------------------------------------------------------------
    // Query helpers
    // -------------------------------------------------------------------------

    /**
     * Build the base authentication query parameters, optionally merging extras.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public function buildQueryParams(array $extra = []): array
    {
        return array_merge([
            'apiKey'         => (string) config('ambient.api_key'),
            'applicationKey' => (string) config('ambient.application_key'),
        ], $extra);
    }

    // -------------------------------------------------------------------------
    // Normalisation
    // -------------------------------------------------------------------------

    /**
     * Convert a raw Ambient API reading into the project's internal format.
     *
     * Field mapping:
     *   dateutc        → recorded_at (Carbon / UTC)
     *   tempf          → temperature (°C)
     *   humidity       → humidity (%)
     *   windspeedmph   → wind_speed (km/h)
     *   winddir        → wind_direction (degrees)
     *   hourlyrainin   → rainfall (mm)
     *   uv             → uv_index
     *   solarradiation → solar_radiation (W/m²)
     *
     * All conversions use null-safe parsing; missing or sentinel values become null.
     *
     * @param  array<string, mixed>  $reading
     * @return array<string, mixed>
     */
    public function normalizeReading(array $reading): array
    {
        return [
            'recorded_at'    => $this->parseTimestamp($reading['dateutc'] ?? null),
            'temperature'    => $this->fahrenheitToCelsius($reading['tempf'] ?? null),
            'humidity'       => $this->safeFloat($reading['humidity'] ?? null),
            'wind_speed'     => $this->mphToKmh($reading['windspeedmph'] ?? null),
            'wind_direction' => $this->safeInt($reading['winddir'] ?? null),
            'rainfall'       => $this->inchesToMm($reading['hourlyrainin'] ?? null),
            'uv_index'       => $this->safeFloat($reading['uv'] ?? null),
            'solar_radiation'=> $this->safeFloat($reading['solarradiation'] ?? null),
            'raw_payload'    => $reading,
        ];
    }

    // -------------------------------------------------------------------------
    // Utility
    // -------------------------------------------------------------------------

    public function isEnabled(): bool
    {
        return (bool) config('ambient.enabled', true)
            && filled(config('ambient.api_key'))
            && filled(config('ambient.application_key'));
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function endpoint(string $path): string
    {
        return rtrim((string) config('ambient.base_url', 'https://api.ambientweather.net/v1'), '/')
            . '/' . ltrim($path, '/');
    }

    private function timeout(): int
    {
        return (int) config('ambient.request_timeout', 20);
    }

    private function cacheTtl(): \DateInterval
    {
        return now()->addMinutes((int) config('ambient.cache_minutes', 10))->diffAsCarbonInterval();
    }

    private function isRateLimitException(Throwable $e): bool
    {
        return str_contains($e->getMessage(), (string) self::RATE_LIMIT_STATUS);
    }

    /**
     * Parse Ambient's dateutc field.
     *
     * Ambient sends either:
     *  - Unix timestamp in milliseconds (integer/string)
     *  - ISO-8601 string
     */
    private function parseTimestamp(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            // Millisecond epoch (13-digit integer)
            if (is_numeric($value) && strlen((string) (int) $value) >= 13) {
                return Carbon::createFromTimestampMs((int) $value, 'UTC')->toDateTimeString();
            }

            return Carbon::parse((string) $value, 'UTC')->toDateTimeString();
        } catch (Throwable) {
            return null;
        }
    }

    private function safeFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $float = (float) $value;

        // Reject Ambient's invalid-reading sentinel
        if ($float <= self::AMBIENT_INVALID_SENTINEL) {
            return null;
        }

        return $float;
    }

    private function safeInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    /** Convert °F to °C, null-safe. */
    private function fahrenheitToCelsius(mixed $fahrenheit): ?float
    {
        $f = $this->safeFloat($fahrenheit);

        return $f !== null ? round(($f - 32) * 5 / 9, 2) : null;
    }

    /** Convert mph to km/h, null-safe. */
    private function mphToKmh(mixed $mph): ?float
    {
        $v = $this->safeFloat($mph);

        return $v !== null ? round($v * 1.60934, 3) : null;
    }

    /** Convert inches to millimetres, null-safe. */
    private function inchesToMm(mixed $inches): ?float
    {
        $v = $this->safeFloat($inches);

        return $v !== null ? round($v * 25.4, 3) : null;
    }
}
