<?php

namespace App\Actions\SolarProjects;

use App\Domain\Climate\ClimateSourceChain;
use App\Domain\Consumption\ApplianceCatalog;
use App\Domain\Consumption\ApplianceLoad;
use App\Domain\Consumption\ConsumptionEstimator;
use App\Domain\Explanation\ExplainedQuestion;
use App\Domain\Explanation\ProjectExplainer;
use App\Domain\Explanation\ProjectFigures;
use App\Domain\Solar\CalculationFreshness;
use App\Models\SolarProject;
use InvalidArgumentException;

/**
 * Use case: answer, in plain language, the questions a client has about their project (ADR-0011).
 */
final class ExplainSolarProject
{
    public function __construct(
        private readonly ProjectExplainer $explainer,
        private readonly ClimateSourceChain $climateSources,
        private readonly ApplianceCatalog $catalog,
        private readonly ConsumptionEstimator $consumptionEstimator,
    ) {}

    /**
     * @return list<ExplainedQuestion>
     */
    public function __invoke(SolarProject $solarProject, CalculationFreshness $freshness): array
    {
        $solarProject->loadMissing(['calculationResult', 'technicalParameter', 'monthlyResults', 'appliances']);
        $result = $solarProject->calculationResult;
        [$biggestLabel, $biggestKwh] = $this->biggestAppliance($solarProject);

        return $this->explainer->explain(new ProjectFigures(
            calculated: $result !== null,
            monthlyConsumptionKwh: $solarProject->monthlyConsumption(),
            energyRateCopKwh: (float) $solarProject->energy_rate_cop_kwh,
            installedCapacityKwp: (float) ($result?->installed_capacity_kwp ?? 0),
            numberOfPanels: (int) ($result?->number_of_panels ?? 0),
            panelAreaM2: (float) ($solarProject->technicalParameter?->panel_area_m2 ?? 0),
            monthlyGenerationKwh: (float) ($result?->estimated_monthly_generation_kwh ?? 0),
            coveragePercentage: (float) ($result?->coverage_percentage ?? 0),
            annualSavingsCop: (float) ($result?->estimated_annual_savings_cop ?? 0),
            installationCostCop: (float) ($result?->installation_cost_cop ?? 0),
            paybackYears: $result?->payback_period_years !== null ? (float) $result->payback_period_years : null,
            months: $solarProject->monthlyResults
                ->sortBy('month_number')
                ->map(fn ($month) => [
                    'name' => (string) $month->month_name,
                    'days' => (int) $month->days_in_month,
                    'generation' => (float) $month->estimated_generation_kwh,
                    'consumption' => (float) $month->estimated_consumption_kwh,
                ])
                ->values()
                ->all(),
            climateSourceKey: $result?->climate_source,
            climateSourceLabel: $this->sourceLabel($result?->climate_source),
            staleReasons: $freshness->status === CalculationFreshness::STALE ? $freshness->reasons : [],
            biggestApplianceLabel: $biggestLabel,
            biggestApplianceKwh: $biggestKwh,
        ));
    }

    private function sourceLabel(?string $key): ?string
    {
        if ($key === null) {
            return null;
        }

        try {
            return $this->climateSources->get($key)->label();
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * The appliance that consumes the most, when the consumption was built from appliances.
     *
     * @return array{0: string|null, 1: float|null}
     */
    private function biggestAppliance(SolarProject $solarProject): array
    {
        $biggest = [null, null];

        foreach ($solarProject->appliances as $appliance) {
            if (! $this->catalog->hasVariant($appliance->appliance_key, $appliance->variant_key)) {
                continue;
            }

            $kwh = $this->consumptionEstimator->monthlyKwh(new ApplianceLoad(
                $appliance->appliance_key,
                $appliance->variant_key,
                (int) $appliance->quantity,
                (float) $appliance->hours_per_day,
            ));

            if ($biggest[1] === null || $kwh > $biggest[1]) {
                $biggest = [$this->catalog->label($appliance->appliance_key), $kwh];
            }
        }

        return $biggest;
    }
}
