<?php

namespace App\Domain\Climate;

use DateTimeInterface;
use InvalidArgumentException;

/**
 * Climate sources ordered by data quality (highest first).
 *
 * The order is configured once in AppServiceProvider; callers either ask for
 * a specific source or for the best one that has data in the range.
 */
final class ClimateSourceChain
{
    /** @var list<ClimateSource> */
    private readonly array $sources;

    public function __construct(ClimateSource ...$sources)
    {
        $this->sources = array_values($sources);
    }

    /**
     * @return list<ClimateSource> Best quality first.
     */
    public function all(): array
    {
        return $this->sources;
    }

    public function get(string $key): ClimateSource
    {
        foreach ($this->sources as $source) {
            if ($source->key() === $key) {
                return $source;
            }
        }

        throw new InvalidArgumentException("Fuente climatica desconocida: {$key}.");
    }

    /**
     * First non-empty series following the priority order, or null when no source has data.
     */
    public function bestAvailable(DateTimeInterface $start, DateTimeInterface $end): ?ClimateSeries
    {
        foreach ($this->sources as $source) {
            $series = $source->dailyIrradiance($start, $end);

            if (! $series->isEmpty()) {
                return $series;
            }
        }

        return null;
    }
}
