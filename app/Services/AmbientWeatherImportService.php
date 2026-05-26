<?php

namespace App\Services;

use App\Models\AmbientWeatherReading;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Ingests Ambient Weather device readings into the local database.
 *
 * Follows the same contract as WeatherStationImportService:
 *  - Returns a summary array {received, created, skipped}
 *  - Never throws for individual-row failures; logs and skips instead
 *  - Prevents duplicates via mac_address + recorded_at unique index
 *
 * The method importLatestForAllDevices() is the main entry point and
 * is called by the SyncAmbientWeather Artisan command.
 */
class AmbientWeatherImportService
{
    public function __construct(
        private readonly AmbientWeatherService $ambientWeatherService,
    ) {}

    /**
     * Fetch the latest reading from every registered Ambient device and
     * persist it to ambient_weather_readings.
     *
     * @return array{received: int, created: int, skipped: int}
     */
    public function importLatestForAllDevices(): array
    {
        $received = 0;
        $created = 0;
        $skipped = 0;

        if (! $this->ambientWeatherService->isEnabled()) {
            Log::info('Ambient Weather import skipped: integration is disabled or credentials are missing.');

            return compact('received', 'created', 'skipped');
        }

        $devices = $this->ambientWeatherService->getDevices();

        if ($devices->isEmpty()) {
            Log::info('Ambient Weather import: no devices returned from API.');

            return compact('received', 'created', 'skipped');
        }

        foreach ($devices as $device) {
            if (! is_array($device)) {
                continue;
            }

            $macAddress = $this->extractMacAddress($device);

            if ($macAddress === null) {
                Log::warning('Ambient Weather import: device entry has no macAddress — skipped.', [
                    'device' => $device,
                ]);
                continue;
            }

            // Use lastData embedded in the /devices response directly — avoids
            // a second API call per station and removes rate-limit pressure.
            $lastData = $device['lastData'] ?? null;

            if (is_array($lastData) && $lastData !== []) {
                [$deviceReceived, $deviceCreated, $deviceSkipped] = $this->importFromLastData($macAddress, $lastData);
            } else {
                // Fall back to individual device endpoint when lastData is absent
                [$deviceReceived, $deviceCreated, $deviceSkipped] = $this->importDevice($macAddress);
            }

            $received += $deviceReceived;
            $created  += $deviceCreated;
            $skipped  += $deviceSkipped;
        }

        return compact('received', 'created', 'skipped');
    }

    /**
     * Import the latest reading for a single device.
     *
     * @return array{0: int, 1: int, 2: int}  [received, created, skipped]
     */
    public function importDevice(string $macAddress): array
    {
        try {
            $reading = $this->ambientWeatherService->getLatestData($macAddress);
        } catch (Throwable $e) {
            Log::warning('Ambient Weather import: failed to fetch latest data for device.', [
                'mac_address' => $macAddress,
                'error'       => $e->getMessage(),
            ]);

            return [0, 0, 0];
        }

        if ($reading === null) {
            return [0, 0, 0];
        }

        $recorded = $reading['recorded_at'] ?? null;

        if ($recorded === null) {
            Log::warning('Ambient Weather import: reading has no recorded_at timestamp — skipped.', [
                'mac_address' => $macAddress,
            ]);

            return [1, 0, 1];
        }

        try {
            $recordedAt = Carbon::parse($recorded, 'UTC');
        } catch (Throwable) {
            return [1, 0, 1];
        }

        // Deduplicate: honour the unique index rather than doing a SELECT first
        // to avoid a race condition under concurrent scheduler runs.
        try {
            $result = AmbientWeatherReading::query()->firstOrCreate(
                ['mac_address' => $macAddress, 'recorded_at' => $recordedAt],
                $this->buildAttributes($macAddress, $recordedAt, $reading),
            );
        } catch (Throwable $e) {
            Log::warning('Ambient Weather import: could not persist reading.', [
                'mac_address' => $macAddress,
                'recorded_at' => $recordedAt->toDateTimeString(),
                'error'       => $e->getMessage(),
            ]);

            return [1, 0, 1];
        }

        $wasCreated = $result->wasRecentlyCreated ? 1 : 0;
        $wasSkipped = $result->wasRecentlyCreated ? 0 : 1;

        return [1, $wasCreated, $wasSkipped];
    }

    /**
     * Persist a reading that came embedded in the /devices list response
     * (device['lastData']). Normalises the raw payload first so units match
     * the rest of the project.
     *
     * @param  array<string, mixed>  $rawLastData
     * @return array{0: int, 1: int, 2: int}  [received, created, skipped]
     */
    public function importFromLastData(string $macAddress, array $rawLastData): array
    {
        $reading = $this->ambientWeatherService->normalizeReading($rawLastData);

        $recorded = $reading['recorded_at'] ?? null;

        if ($recorded === null) {
            Log::warning('Ambient Weather import: lastData has no dateutc — skipped.', [
                'mac_address' => $macAddress,
            ]);

            return [1, 0, 1];
        }

        try {
            $recordedAt = Carbon::parse($recorded, 'UTC');
        } catch (Throwable) {
            return [1, 0, 1];
        }

        try {
            $result = AmbientWeatherReading::query()->firstOrCreate(
                ['mac_address' => $macAddress, 'recorded_at' => $recordedAt],
                $this->buildAttributes($macAddress, $recordedAt, $reading),
            );
        } catch (Throwable $e) {
            Log::warning('Ambient Weather import: could not persist lastData reading.', [
                'mac_address' => $macAddress,
                'recorded_at' => $recordedAt->toDateTimeString(),
                'error'       => $e->getMessage(),
            ]);

            return [1, 0, 1];
        }

        return [1, $result->wasRecentlyCreated ? 1 : 0, $result->wasRecentlyCreated ? 0 : 1];
    }

    /**
     * Import up to one year of historical data for all registered devices.
     *
     * Paginates the Ambient Weather API backwards in 288-reading blocks
     * (~24 h each) from $to down to $from, persisting each batch.
     *
     * @return array{received: int, created: int, skipped: int}
     */
    public function importHistoricalForAllDevices(
        CarbonInterface $from,
        ?CarbonInterface $to = null,
        int $sleepSeconds = 1,
    ): array {
        $received = 0;
        $created  = 0;
        $skipped  = 0;

        if (! $this->ambientWeatherService->isEnabled()) {
            Log::info('Ambient Weather historical import skipped: disabled or missing credentials.');

            return compact('received', 'created', 'skipped');
        }

        $devices = $this->ambientWeatherService->getDevices();

        if ($devices->isEmpty()) {
            Log::info('Ambient Weather historical import: no devices returned from API.');

            return compact('received', 'created', 'skipped');
        }

        $to ??= Carbon::yesterday('UTC')->endOfDay();

        foreach ($devices as $device) {
            if (! is_array($device)) {
                continue;
            }

            $mac = ($device['macAddress'] ?? $device['mac_address'] ?? null);

            if (! is_string($mac) || ! filled($mac)) {
                continue;
            }

            [$r, $c, $s] = $this->importHistoricalForDevice(trim($mac), $from, $to, $sleepSeconds);
            $received += $r;
            $created  += $c;
            $skipped  += $s;
        }

        return compact('received', 'created', 'skipped');
    }

    /**
     * Walk backwards through the API for a single device, batch by batch.
     *
     * @return array{0: int, 1: int, 2: int}  [received, created, skipped]
     */
    public function importHistoricalForDevice(
        string $macAddress,
        CarbonInterface $from,
        CarbonInterface $to,
        int $sleepSeconds = 1,
    ): array {
        $received = 0;
        $created  = 0;
        $skipped  = 0;
        $endDate  = Carbon::instance($to);

        while ($endDate->greaterThan($from)) {
            try {
                $readings = $this->ambientWeatherService->getHistoricalData(
                    $macAddress,
                    $from,
                    $endDate,
                    288,
                );
            } catch (Throwable $e) {
                Log::warning('Ambient Weather historical import: batch fetch failed.', [
                    'mac_address' => $macAddress,
                    'end_date'    => $endDate->toDateTimeString(),
                    'error'       => $e->getMessage(),
                ]);
                // Back off and retry once
                sleep(5);

                try {
                    $readings = $this->ambientWeatherService->getHistoricalData(
                        $macAddress, $from, $endDate, 288,
                    );
                } catch (Throwable) {
                    break;
                }
            }

            if ($readings->isEmpty()) {
                break;
            }

            $received += $readings->count();

            // Track oldest timestamp to know where to move endDate next
            $oldestTs = null;

            foreach ($readings as $reading) {
                $recorded = $reading['recorded_at'] ?? null;

                if ($recorded === null) {
                    $skipped++;
                    continue;
                }

                try {
                    $recordedAt = Carbon::parse($recorded, 'UTC');
                } catch (Throwable) {
                    $skipped++;
                    continue;
                }

                if ($oldestTs === null || $recordedAt->lessThan($oldestTs)) {
                    $oldestTs = $recordedAt->copy();
                }

                try {
                    $result = AmbientWeatherReading::query()->firstOrCreate(
                        ['mac_address' => $macAddress, 'recorded_at' => $recordedAt],
                        $this->buildAttributes($macAddress, $recordedAt, $reading),
                    );
                    $result->wasRecentlyCreated ? $created++ : $skipped++;
                } catch (Throwable $e) {
                    Log::warning('Ambient Weather historical import: could not persist reading.', [
                        'mac_address' => $macAddress,
                        'recorded_at' => $recordedAt->toDateTimeString(),
                        'error'       => $e->getMessage(),
                    ]);
                    $skipped++;
                }
            }

            // Move endDate back past the oldest reading in this batch
            if ($oldestTs === null || $oldestTs->lessThanOrEqualTo($from)) {
                break;
            }

            $endDate = $oldestTs->subMinute();

            if ($sleepSeconds > 0) {
                sleep($sleepSeconds);
            }
        }

        return [$received, $created, $skipped];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $reading  Already normalised by AmbientWeatherService
     * @return array<string, mixed>
     */
    private function buildAttributes(
        string $macAddress,
        \Carbon\CarbonInterface $recordedAt,
        array $reading,
    ): array {
        return [
            'mac_address'    => $macAddress,
            'recorded_at'    => $recordedAt,
            'raw_payload'    => $reading['raw_payload'] ?? null,
            'temperature'    => $reading['temperature'] ?? null,
            'humidity'       => $reading['humidity'] ?? null,
            'wind_speed'     => $reading['wind_speed'] ?? null,
            'wind_direction' => $reading['wind_direction'] ?? null,
            'rainfall'       => $reading['rainfall'] ?? null,
            'uv_index'       => $reading['uv_index'] ?? null,
            'solar_radiation'=> $reading['solar_radiation'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $device
     */
    private function extractMacAddress(array $device): ?string
    {
        $mac = $device['macAddress'] ?? $device['mac_address'] ?? $device['mac'] ?? null;

        return is_string($mac) && filled($mac) ? trim($mac) : null;
    }
}
