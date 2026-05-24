<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class NasaPowerService
{
    private const DEFAULT_DAILY_PARAMETERS = [
        'ALLSKY_SFC_SW_DWN',
        'T2M',
        'RH2M',
        'PRECTOTCORR',
        'WS10M',
    ];

    private const SUPPORTED_GRANULARITIES = ['daily', 'monthly', 'hourly'];

    /**
     * @return array<string, mixed>
     */
    public function fetchDailyData(CarbonInterface|string $startDate, CarbonInterface|string $endDate): array
    {
        return $this->fetchTemporalPointData('daily', $startDate, $endDate, self::DEFAULT_DAILY_PARAMETERS);
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchHourlyData(CarbonInterface|string $startDate, CarbonInterface|string $endDate): array
    {
        return $this->fetchTemporalPointData('hourly', $startDate, $endDate, self::DEFAULT_DAILY_PARAMETERS);
    }

    /**
     * @param  array<int, string>  $parameters
     * @return array<string, mixed>
     */
    public function fetchTemporalPointData(
        string $granularity,
        CarbonInterface|string $startDate,
        CarbonInterface|string $endDate,
        array $parameters,
    ): array {
        $granularity = strtolower(trim($granularity));

        if (! in_array($granularity, self::SUPPORTED_GRANULARITIES, true)) {
            throw new InvalidArgumentException("Unsupported NASA POWER granularity [{$granularity}].");
        }

        $parameters = $this->validatedParameters($parameters);
        [$start, $end] = $this->availableDateRange($granularity, $startDate, $endDate);
        $coordinates = $this->validatedCoordinates();
        $endpoint = rtrim((string) config('services.nasa_power.base_url', 'https://power.larc.nasa.gov/api/temporal'), '/')
            . "/{$granularity}/point";

        $response = Http::timeout(30)
            ->retry(2, 500)
            ->withOptions([
                'verify' => (bool) config('services.nasa_power.verify_ssl', false),
            ])
            ->get($endpoint, [
                'parameters' => implode(',', $parameters),
                'community' => (string) config('services.nasa_power.community', 'SB'),
                'longitude' => $coordinates['longitude'],
                'latitude' => $coordinates['latitude'],
                'start' => $start,
                'end' => $end,
                'time-standard' => (string) config('services.nasa_power.time_standard', 'LST'),
                'format' => (string) config('services.nasa_power.format', 'JSON'),
            ]);

        try {
            $response->throw();
        } catch (\Throwable $exception) {
            throw new RuntimeException(
                'NASA POWER request failed with status '.$response->status().'. Body: '.mb_substr($response->body(), 0, 500),
                0,
                $exception
            );
        }

        $payload = $response->json();
        $this->assertValidPayload($payload);

        return $payload;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function availableDateRange(
        string $granularity,
        CarbonInterface|string $startDate,
        CarbonInterface|string $endDate
    ): array {
        $timezone = config('app.timezone');
        $start = $this->parseDate($startDate);
        $end = $this->parseDate($endDate);
        $latestAvailableDate = match ($granularity) {
            'daily', 'hourly' => CarbonImmutable::yesterday($timezone)->startOfDay(),
            'monthly' => CarbonImmutable::now($timezone)->startOfMonth()->subMonth(),
            default => throw new InvalidArgumentException("Unsupported NASA POWER granularity [{$granularity}]."),
        };

        if ($end->greaterThan($latestAvailableDate)) {
            $end = $latestAvailableDate;
        }

        if ($start->greaterThan($end)) {
            throw new InvalidArgumentException('NASA POWER data is not available for future-only ranges.');
        }

        if ($granularity === 'monthly') {
            return [$start->format('Ym'), $end->format('Ym')];
        }

        return [$start->format('Ymd'), $end->format('Ymd')];
    }

    private function parseDate(CarbonInterface|string $date): CarbonImmutable
    {
        if ($date instanceof CarbonInterface) {
            return CarbonImmutable::instance($date)->startOfDay();
        }

        return CarbonImmutable::parse($date, config('app.timezone'))->startOfDay();
    }

    /**
     * @param  array<int, string>  $parameters
     * @return array<int, string>
     */
    private function validatedParameters(array $parameters): array
    {
        $clean = collect($parameters)
            ->map(fn (mixed $parameter) => strtoupper(trim((string) $parameter)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($clean === [] || count($clean) > 20) {
            throw new InvalidArgumentException('NASA POWER requires between 1 and 20 parameters per point request.');
        }

        return $clean;
    }

    /**
     * @return array{latitude: float, longitude: float}
     */
    private function validatedCoordinates(): array
    {
        $latitude = (float) config('services.nasa_power.latitude', 11.5444);
        $longitude = (float) config('services.nasa_power.longitude', -72.9072);

        if ($latitude < -90 || $latitude > 90) {
            throw new InvalidArgumentException("Invalid latitude [{$latitude}] configured for NASA POWER.");
        }

        if ($longitude < -180 || $longitude > 180) {
            throw new InvalidArgumentException("Invalid longitude [{$longitude}] configured for NASA POWER.");
        }

        return ['latitude' => $latitude, 'longitude' => $longitude];
    }

    /**
     * @param  mixed  $payload
     */
    private function assertValidPayload(mixed $payload): void
    {
        if (! is_array($payload)) {
            throw new RuntimeException('NASA POWER returned an invalid JSON payload.');
        }

        $parameterData = data_get($payload, 'properties.parameter');

        if (! is_array($parameterData) || $parameterData === []) {
            throw new RuntimeException('NASA POWER payload does not include parameter data.');
        }
    }
}
