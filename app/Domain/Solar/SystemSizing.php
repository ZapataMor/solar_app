<?php

namespace App\Domain\Solar;

/**
 * How many panels a project needs, how many fit on its roof and how many are installed (ADR-0014).
 *
 * The system is sized to the consumption, not to the roof: installing more panels than needed only
 * raises the cost. When the roof is too small, every panel that fits is installed and the rest of the
 * consumption keeps coming from the grid.
 */
final readonly class SystemSizing
{
    public function __construct(
        public float $monthlyConsumptionKwh,
        public float $panelMonthlyKwh,
        public int $panelsThatFit,
        public int $panelsNeeded,
        public int $panelsInstalled,
    ) {}

    /**
     * @param  float  $panelMonthlyKwh  What one panel produces in a month in this place.
     */
    public static function for(float $monthlyConsumptionKwh, float $panelMonthlyKwh, int $panelsThatFit): self
    {
        $needed = $monthlyConsumptionKwh > 0 && $panelMonthlyKwh > 0
            ? (int) ceil(round($monthlyConsumptionKwh / $panelMonthlyKwh, 6))
            : 0;
        $fit = max(0, $panelsThatFit);

        return new self($monthlyConsumptionKwh, $panelMonthlyKwh, $fit, $needed, min($needed, $fit));
    }

    public function monthlyGenerationKwh(): float
    {
        return $this->panelsInstalled * $this->panelMonthlyKwh;
    }

    /**
     * Share of the consumption the installed panels cover (it can pass 100 %: panels come whole).
     */
    public function coveragePercentage(): float
    {
        return $this->monthlyConsumptionKwh > 0 ? $this->monthlyGenerationKwh() / $this->monthlyConsumptionKwh * 100 : 0.0;
    }

    public function roofIsEnough(): bool
    {
        return $this->panelsNeeded <= $this->panelsThatFit;
    }

    /**
     * Panels that would be needed but do not fit.
     */
    public function missingPanels(): int
    {
        return max(0, $this->panelsNeeded - $this->panelsThatFit);
    }

    /**
     * Room left on the roof for more panels (e.g. if the client adds appliances later).
     */
    public function sparePanels(): int
    {
        return $this->panelsThatFit - $this->panelsInstalled;
    }

    /**
     * Energy that would still come from the grid each month.
     */
    public function uncoveredMonthlyKwh(): float
    {
        return max(0.0, $this->monthlyConsumptionKwh - $this->monthlyGenerationKwh());
    }
}
