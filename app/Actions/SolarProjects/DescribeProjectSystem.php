<?php

namespace App\Actions\SolarProjects;

use App\Domain\Consumption\ApplianceCatalog;
use App\Domain\Explanation\ProjectExplainer;
use App\Domain\Solar\LiveSolarOutput;
use App\Domain\Solar\SystemSizing;
use App\Models\AmbientWeatherReading;
use App\Models\SolarProject;
use App\Models\SolarProjectAppliance;
use Carbon\CarbonInterface;

/**
 * Use case: everything the alternative panel ("Mi sistema", ADR-0014) shows, in plain terms:
 * how many panels, how much of the consumption they cover, what it costs and saves, month by
 * month, and what the panels would be doing right now.
 */
final class DescribeProjectSystem
{
    /** A station reading older than this is not "now" any more. */
    private const LIVE_READING_MAX_AGE_MINUTES = 180;

    public function __construct(
        private readonly SizeProjectSystem $sizeProjectSystem,
        private readonly ApplianceCatalog $catalog,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(SolarProject $solarProject): array
    {
        $solarProject->loadMissing(['technicalParameter', 'calculationResult', 'monthlyResults', 'appliances']);
        $result = $solarProject->calculationResult;
        $rate = max(0.0, (float) $solarProject->energy_rate_cop_kwh);
        $consumption = $solarProject->monthlyConsumption();
        $live = ($this->sizeProjectSystem)($solarProject);

        // The calculation keeps its own sizing; older calculations (before ADR-0014) did not store it.
        $storedSizing = $result !== null && $result->panels_needed !== null && $result->panel_monthly_generation_kwh !== null
            ? new SystemSizing(
                monthlyConsumptionKwh: (float) $result->annual_consumption_kwh / 12,
                panelMonthlyKwh: (float) $result->panel_monthly_generation_kwh,
                panelsThatFit: (int) $result->panels_that_fit,
                panelsNeeded: (int) $result->panels_needed,
                panelsInstalled: (int) $result->number_of_panels,
            )
            : null;
        $sizing = $storedSizing ?? $live['sizing'] ?? null;
        $monthlyGeneration = $result !== null ? (float) $result->estimated_monthly_generation_kwh : ($sizing?->monthlyGenerationKwh() ?? 0.0);
        $uncoveredKwh = max(0.0, $consumption - $monthlyGeneration);

        return [
            'calculated' => $result !== null,
            'hasConsumption' => $consumption > 0,
            'sizing' => $sizing,
            'sizingIsEstimate' => $storedSizing === null,
            'rate' => $rate,
            'consumptionKwh' => $consumption,
            'billCop' => $consumption * $rate,
            'generationKwh' => $monthlyGeneration,
            'coveragePercentage' => $result !== null ? (float) $result->coverage_percentage : ($sizing?->coveragePercentage() ?? 0.0),
            'savingsCop' => $result !== null ? (float) $result->estimated_annual_savings_cop / 12 : 0.0,
            'uncoveredKwh' => $uncoveredKwh,
            'uncoveredCop' => $uncoveredKwh * $rate,
            'installationCostCop' => $result !== null ? (float) $result->installation_cost_cop : null,
            'paybackYears' => $result?->payback_period_years !== null ? (float) $result->payback_period_years : null,
            'installedCapacityKwp' => $result !== null ? (float) $result->installed_capacity_kwp : null,
            'months' => $this->months($solarProject, $rate),
            'live' => $this->live($solarProject, $result?->installed_capacity_kwp),
            'scene' => [
                'propertyType' => $solarProject->property_type,
                'panelsInstalled' => $sizing?->panelsInstalled ?? 0,
                'panelsMissing' => $sizing?->missingPanels() ?? 0,
                'roofAreaM2' => (float) ($solarProject->technicalParameter?->available_area_m2 ?? 0),
                'panelAreaM2' => (float) ($solarProject->technicalParameter?->panel_area_m2 ?? 0),
            ],
        ];
    }

    /**
     * Sun vs. appliances per month, both scaled to a 30-day month (a month may have only some days
     * of climate data). Months with too few measured days are left out (see CLAUDE.md).
     *
     * @return list<array{label: string, name: string, sunKwh: float, useKwh: float, sunCop: float, useCop: float}>
     */
    private function months(SolarProject $solarProject, float $rate): array
    {
        return $solarProject->monthlyResults
            ->sortBy('month_number')
            ->filter(fn ($month) => (int) $month->days_in_month >= ProjectExplainer::MIN_DAYS_TO_COMPARE_A_MONTH)
            ->map(function ($month) use ($rate) {
                $sun = (float) $month->estimated_generation_kwh / (int) $month->days_in_month * 30;
                $use = (float) $month->estimated_consumption_kwh;

                return [
                    'label' => mb_convert_case(mb_substr((string) $month->month_name, 0, 3), MB_CASE_TITLE),
                    'name' => mb_convert_case((string) $month->month_name, MB_CASE_TITLE),
                    'sunKwh' => $sun,
                    'useKwh' => $use,
                    'sunCop' => $sun * $rate,
                    'useCop' => $use * $rate,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{output: LiveSolarOutput, recordedAt: CarbonInterface}|null
     */
    private function live(SolarProject $solarProject, mixed $installedCapacityKwp): ?array
    {
        if ($installedCapacityKwp === null || (float) $installedCapacityKwp <= 0) {
            return null;
        }

        $reading = AmbientWeatherReading::query()
            ->whereNotNull('solar_radiation')
            ->latest('recorded_at')
            ->first();

        if ($reading === null || $reading->recorded_at->diffInMinutes(now(), true) > self::LIVE_READING_MAX_AGE_MINUTES) {
            return null;
        }

        $appliances = $solarProject->appliances
            ->filter(fn (SolarProjectAppliance $appliance) => $this->catalog->hasVariant($appliance->appliance_key, $appliance->variant_key))
            ->map(fn (SolarProjectAppliance $appliance) => [
                'label' => $this->catalog->label($appliance->appliance_key),
                'watts' => $this->catalog->watts($appliance->appliance_key, $appliance->variant_key) * (int) $appliance->quantity,
            ])
            ->values()
            ->all();

        return [
            'output' => LiveSolarOutput::for(
                (float) $installedCapacityKwp,
                (float) $reading->solar_radiation,
                (float) ($solarProject->technicalParameter?->performance_ratio ?? 0.85),
                $appliances,
            ),
            'recordedAt' => $reading->recorded_at,
        ];
    }
}
