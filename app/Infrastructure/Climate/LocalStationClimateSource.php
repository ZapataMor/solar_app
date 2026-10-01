<?php

namespace App\Infrastructure\Climate;

use App\Domain\Climate\ClimateSeries;
use App\Domain\Climate\ClimateSource;
use App\Models\WeatherStationReading;
use App\Services\WeatherStationAggregationService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
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

    public function lastChangedAt(DateTimeInterface $start, DateTimeInterface $end): ?DateTimeImmutable
    {
        $latest = WeatherStationReading::query()
            ->whereBetween('measured_at', [$start, $end])
            ->max('updated_at');

        return $latest !== null ? CarbonImmutable::parse($latest) : null;
    }
}
