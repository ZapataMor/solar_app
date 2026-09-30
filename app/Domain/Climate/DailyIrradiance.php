<?php

namespace App\Domain\Climate;

/**
 * 24h-average global horizontal irradiance for one calendar day.
 *
 * Every climate source normalizes its readings to this shape, so the solar
 * calculator never needs to know where the data came from.
 */
final readonly class DailyIrradiance
{
    public function __construct(
        public string $date,
        public float $averageWm2,
    ) {}

    public function month(): int
    {
        return (int) substr($this->date, 5, 2);
    }

    /**
     * Peak sun hours (kWh/m²/day): the 24h-average W/m² × 24 / 1000.
     */
    public function peakSunHours(): float
    {
        return $this->averageWm2 * 24 / 1000;
    }
}
