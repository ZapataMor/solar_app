<?php

namespace App\Infrastructure\Climate;

use App\Domain\Climate\ClimateSeries;
use App\Domain\Climate\ClimateSource;
use App\Services\AmbientWeatherAggregationService;
use Carbon\Carbon;
use DateTimeInterface;

final class AmbientWeatherClimateSource implements ClimateSource
{
    public function __construct(
        private readonly AmbientWeatherAggregationService $aggregation,
    ) {}

    public function key(): string
    {
        return self::AMBIENT;
    }

    public function label(): string
    {
        return 'Ambient Weather';
    }

    public function dailyIrradiance(DateTimeInterface $start, DateTimeInterface $end): ClimateSeries
    {
        $rows = $this->aggregation->dailyRowsForRange(Carbon::instance($start), Carbon::instance($end));

        return new ClimateSeries(
            source: $this->key(),
            days: IrradianceRows::toDays($rows),
            metadata: [
                'average_temperature_correction' => $rows->isNotEmpty()
                    ? (float) $rows->avg(fn (array $row) => $row['temp_correction'] ?? 1.0)
                    : null,
            ],
        );
    }
}
