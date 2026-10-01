<?php

namespace App\Domain\Explanation;

/**
 * The project numbers the explainer translates into plain language (the same ones the panel shows).
 */
final readonly class ProjectFigures
{
    /**
     * @param  list<array{name: string, days: int, generation: float, consumption: float}>  $months  Generation covers only the `days` with climate data.
     * @param  list<string>  $staleReasons  Why the calculation may be outdated (empty when up to date).
     */
    public function __construct(
        public bool $calculated,
        public float $monthlyConsumptionKwh,
        public float $energyRateCopKwh,
        public float $installedCapacityKwp = 0,
        public int $numberOfPanels = 0,
        public float $panelAreaM2 = 0,
        public float $monthlyGenerationKwh = 0,
        public float $coveragePercentage = 0,
        public float $annualSavingsCop = 0,
        public float $installationCostCop = 0,
        public ?float $paybackYears = null,
        public array $months = [],
        public ?string $climateSourceKey = null,
        public ?string $climateSourceLabel = null,
        public array $staleReasons = [],
        public ?string $biggestApplianceLabel = null,
        public ?float $biggestApplianceKwh = null,
    ) {}

    public function monthlyBillCop(): float
    {
        return $this->monthlyConsumptionKwh * $this->energyRateCopKwh;
    }

    public function monthlySavingsCop(): float
    {
        return $this->annualSavingsCop / 12;
    }
}
