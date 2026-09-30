<?php

namespace Tests\Unit\Domain;

use App\Domain\Climate\ClimateSeries;
use App\Domain\Climate\ClimateSource;
use App\Domain\Climate\ClimateSourceChain;
use App\Domain\Climate\DailyIrradiance;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ClimateSourceChainTest extends TestCase
{
    public function test_best_available_skips_sources_without_data(): void
    {
        $chain = new ClimateSourceChain(
            $this->source(ClimateSource::AMBIENT, []),
            $this->source(ClimateSource::LOCAL, [new DailyIrradiance('2026-01-01', 200)]),
            $this->source(ClimateSource::NASA_POWER, [new DailyIrradiance('2026-01-01', 180)]),
        );

        $series = $chain->bestAvailable(new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-01-31'));

        $this->assertSame(ClimateSource::LOCAL, $series?->source);
    }

    public function test_best_available_returns_null_when_no_source_has_data(): void
    {
        $chain = new ClimateSourceChain($this->source(ClimateSource::NASA_POWER, []));

        $this->assertNull($chain->bestAvailable(new DateTimeImmutable, new DateTimeImmutable));
    }

    public function test_get_rejects_unknown_sources(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ClimateSourceChain)->get('satelite-inventado');
    }

    /**
     * @param  list<DailyIrradiance>  $days
     */
    private function source(string $key, array $days): ClimateSource
    {
        return new class($key, $days) implements ClimateSource
        {
            /** @param list<DailyIrradiance> $days */
            public function __construct(private string $sourceKey, private array $days) {}

            public function key(): string
            {
                return $this->sourceKey;
            }

            public function label(): string
            {
                return $this->sourceKey;
            }

            public function dailyIrradiance(DateTimeInterface $start, DateTimeInterface $end): ClimateSeries
            {
                return new ClimateSeries($this->sourceKey, $this->days);
            }
        };
    }
}
