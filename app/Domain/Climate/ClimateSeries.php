<?php

namespace App\Domain\Climate;

/**
 * Daily irradiance produced by one climate source for a date range.
 */
final readonly class ClimateSeries
{
    /**
     * @param  list<DailyIrradiance>  $days
     * @param  array<string, mixed>  $metadata  Source-specific extras (e.g. average thermal correction).
     */
    public function __construct(
        public string $source,
        public array $days,
        public array $metadata = [],
    ) {}

    public function isEmpty(): bool
    {
        return $this->days === [];
    }

    public function dayCount(): int
    {
        return count($this->days);
    }
}
