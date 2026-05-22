<?php

namespace App\Services;

use App\Models\SolarProject;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;

class NasaPowerService
{
    private const ENDPOINT = 'https://power.larc.nasa.gov/api/temporal/hourly/point';

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
    public function fetchHourlyData(CarbonInterface|string $startDate, CarbonInterface|string $endDate): array
    {
        $response = Http::timeout(30)
            ->retry(2, 500)
            ->get(self::ENDPOINT, [
                'parameters' => implode(',', self::PARAMETERS),
                'community' => 'SB',
                'longitude' => SolarProject::LONGITUDE,
                'latitude' => SolarProject::LATITUDE,
                'start' => $this->formatDate($startDate),
                'end' => $this->formatDate($endDate),
                'format' => 'JSON',
            ]);

        $response->throw();

        return $response->json();
    }

    private function formatDate(CarbonInterface|string $date): string
    {
        if ($date instanceof CarbonInterface) {
            return $date->format('Ymd');
        }

        return date('Ymd', strtotime($date));
    }
}
