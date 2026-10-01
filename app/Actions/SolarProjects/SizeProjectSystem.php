<?php

namespace App\Actions\SolarProjects;

use App\Domain\Solar\RequiredPower;
use App\Domain\Solar\SolarCalculator;
use App\Domain\Solar\SystemSizing;
use App\Domain\Solar\SystemSpecification;
use App\Models\SolarProject;

/**
 * Use case: panels needed, panels that fit and coverage for the project's CURRENT appliances
 * (ADR-0014). It answers live while the client fills the diary, before or after calculating.
 *
 * A panel's production comes from the last climate-based calculation; without one, from the
 * reference sun of La Guajira (and the result says it is an estimate).
 */
final class SizeProjectSystem
{
    public function __construct(
        private readonly SolarCalculator $calculator,
    ) {}

    /**
     * @return array{sizing: SystemSizing, fromClimateData: bool}|null Null without technical parameters.
     */
    public function __invoke(SolarProject $solarProject): ?array
    {
        $solarProject->loadMissing(['technicalParameter', 'calculationResult']);
        $parameters = $solarProject->technicalParameter;

        if ($parameters === null) {
            return null;
        }

        $system = new SystemSpecification(
            availableAreaM2: (float) $parameters->available_area_m2,
            usableAreaPercentage: (float) $parameters->usable_area_percentage,
            panelAreaM2: (float) $parameters->panel_area_m2,
            panelPowerW: (float) $parameters->panel_power_w,
            performanceRatio: (float) $parameters->performance_ratio,
        );

        $climatePanelKwh = (float) ($solarProject->calculationResult?->panel_monthly_generation_kwh ?? 0);
        $fromClimateData = $climatePanelKwh > 0;

        return [
            'sizing' => SystemSizing::for(
                $solarProject->monthlyConsumption(),
                $fromClimateData ? $climatePanelKwh : $this->calculator->panelMonthlyGenerationKwh($system, RequiredPower::REFERENCE_DAILY_HSP),
                $this->calculator->numberOfPanels(
                    $this->calculator->usableArea($system->availableAreaM2, $system->usableAreaPercentage),
                    $system->panelAreaM2,
                ),
            ),
            'fromClimateData' => $fromClimateData,
        ];
    }
}
