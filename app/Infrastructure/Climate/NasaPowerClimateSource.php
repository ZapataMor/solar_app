<?php

namespace App\Infrastructure\Climate;

use App\Domain\Climate\ClimateSeries;
use App\Domain\Climate\ClimateSource;
use App\Models\ApiWeatherData;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeInterface;

final class NasaPowerClimateSource implements ClimateSource
{
    public function key(): string
    {
        return self::NASA_POWER;
    }

    public function label(): string
    {
        return 'NASA POWER';
    }

    /**
     * NASA POWER stores ALLSKY_SFC_SW_DWN as a 24h-average W/m², which is
     * already the shape the domain expects.
     */
    public function dailyIrradiance(DateTimeInterface $start, DateTimeInterface $end): ClimateSeries
    {
        $rows = ApiWeatherData::query()
            ->select(['date_time', 'allsky_sfc_sw_dwn'])
            ->whereBetween('date_time', [$start, $end])
            ->orderBy('date_time')
            ->get();

        return new ClimateSeries(source: $this->key(), days: IrradianceRows::toDays($rows));
    }

    public function lastChangedAt(DateTimeInterface $start, DateTimeInterface $end): ?DateTimeImmutable
    {
        $latest = ApiWeatherData::query()
            ->whereBetween('date_time', [$start, $end])
            ->max('updated_at');

        return $latest !== null ? CarbonImmutable::parse($latest) : null;
    }
}
