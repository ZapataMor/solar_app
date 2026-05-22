<?php

namespace App\Services;

use App\Models\SolarProject;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class NasaPowerService
{
    private const ENDPOINT = 'https://power.larc.nasa.gov/api/temporal/daily/point';

    private const PARAMETERS = [
        'ALLSKY_SFC_SW_DWN',
        'T2M',
        'RH2M',
        'PRECTOTCORR',
        'WS10M',
    ];

    /**
     * @return array<string, mixed>
     */
    public function fetchDailyData(CarbonInterface|string $startDate, CarbonInterface|string $endDate): array
    {
        [$start, $end] = $this->availableDateRange($startDate, $endDate);

        $response = Http::timeout(30)
            ->retry(2, 500)
            ->withOptions([
                'verify' => (bool) config('services.nasa_power.verify_ssl', false),
            ])
            ->get(self::ENDPOINT, [
                'parameters' => implode(',', self::PARAMETERS),
                'community' => 'SB',
                'longitude' => SolarProject::LONGITUDE,
                'latitude' => SolarProject::LATITUDE,
                'start' => $start,
                'end' => $end,
                'time-standard' => 'LST',
                'format' => 'JSON',
            ]);

        $response->throw();

        return $response->json();
    }

    /**
     * NASA POWER daily data is historical/NRT, so future end dates must be
     * capped before the request reaches the API.
     *
     * @return array{0: string, 1: string}
     */
    private function availableDateRange(CarbonInterface|string $startDate, CarbonInterface|string $endDate): array
    {
        $start = $this->parseDate($startDate);
        $end = $this->parseDate($endDate);
        $latestAvailableDate = CarbonImmutable::yesterday(config('app.timezone'));

        if ($end->greaterThan($latestAvailableDate)) {
            $end = $latestAvailableDate;
        }

        if ($start->greaterThan($end)) {
            throw new InvalidArgumentException('NASA POWER daily data is not available for future-only ranges.');
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
}
