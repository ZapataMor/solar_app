<?php

namespace App\Domain\Solar;

/**
 * Outcome of sizing a photovoltaic system against a climate series.
 */
final readonly class SolarEstimate
{
    /**
     * @param  list<MonthlyGeneration>  $months
     */
    public function __construct(
        public float $usableAreaM2,
        public int $numberOfPanels,
        public float $installedCapacityKwp,
        public float $dailyGenerationKwh,
        public float $monthlyGenerationKwh,
        public float $annualGenerationKwh,
        public float $annualConsumptionKwh,
        public float $coveragePercentage,
        public float $annualSavingsCop,
        public float $installationCostCop,
        public ?float $paybackPeriodYears,
        public array $months,
        public ?SystemSizing $sizing = null,
    ) {}
}
