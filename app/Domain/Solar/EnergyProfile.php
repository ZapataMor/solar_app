<?php

namespace App\Domain\Solar;

/**
 * What the client consumes and pays for energy.
 */
final readonly class EnergyProfile
{
    public function __construct(
        public float $monthlyConsumptionKwh,
        public float $energyRateCopKwh,
        public int $annualProjectionDays = 365,
    ) {}

    public function dailyConsumptionKwh(): float
    {
        return $this->monthlyConsumptionKwh / 30;
    }

    public function annualConsumptionKwh(): float
    {
        return $this->monthlyConsumptionKwh * 12;
    }
}
