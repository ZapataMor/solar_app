<?php

namespace App\Infrastructure\Climate;

use App\Domain\Climate\ClimateSeries;
use App\Domain\Climate\ClimateSource;
use App\Services\WeatherStationAggregationService;
use Carbon\Carbon;
use DateTimeInterface;

final class LocalStationClimateSource implements ClimateSource
{
    public function __construct(
        private readonly WeatherStationAggregationService $aggregation,
    ) {}

    public function key(): string
    {
        return self::LOCAL;
    }

    public function label(): string
    {
        return 'Centro meteorologico';
    }

    public function dailyIrradiance(DateTimeInterface $start, DateTimeInterface $end): ClimateSeries
    {
        $readings = $this->aggregation->readingsForRange(Carbon::instance($start), Carbon::instance($end));

        return new ClimateSeries(
            source: $this->key(),
            days: IrradianceRows::toDays($this->aggregation->dailyRows($readings)),
        );
    }
}
