<?php

namespace App\Actions\SolarProjects;

use App\Domain\Climate\ClimateSeries;
use App\Domain\Climate\ClimateSourceChain;
use App\Domain\Climate\NoClimateData;
use App\Domain\Solar\EnergyProfile;
use App\Domain\Solar\MissingTechnicalParameters;
use App\Domain\Solar\MonthlyGeneration;
use App\Domain\Solar\SolarCalculator;
use App\Domain\Solar\SolarEstimate;
use App\Domain\Solar\SystemSpecification;
use App\Models\SolarProject;
use Illuminate\Support\Facades\DB;

/**
 * Use case: size a project's PV system with climate data and store the results.
 *
 * With no source it follows the quality priority (Ambient → local station →
 * NASA POWER); with a source key it uses only that one.
 */
final class CalculateSolarProject
{
    public function __construct(
        private readonly ClimateSourceChain $climateSources,
        private readonly SolarCalculator $calculator,
    ) {}

    /**
     * @return ClimateSeries The climate data the calculation was based on.
     *
     * @throws MissingTechnicalParameters
     * @throws NoClimateData
     */
    public function __invoke(SolarProject $solarProject, ?string $source = null): ClimateSeries
    {
        $technicalParameter = $solarProject->technicalParameter()->first();

        if ($technicalParameter === null) {
            throw new MissingTechnicalParameters;
        }

        $start = $solarProject->start_date->copy()->startOfDay();
        $end = $solarProject->end_date->copy()->endOfDay();

        $series = $source === null
            ? $this->climateSources->bestAvailable($start, $end)
            : $this->climateSources->get($source)->dailyIrradiance($start, $end);

        if ($series === null || $series->isEmpty()) {
            throw new NoClimateData($source);
        }

        $estimate = $this->calculator->estimate(
            new SystemSpecification(
                availableAreaM2: (float) $technicalParameter->available_area_m2,
                usableAreaPercentage: (float) $technicalParameter->usable_area_percentage,
                panelAreaM2: (float) $technicalParameter->panel_area_m2,
                panelPowerW: (float) $technicalParameter->panel_power_w,
                performanceRatio: (float) $technicalParameter->performance_ratio,
            ),
            new EnergyProfile(
                monthlyConsumptionKwh: $solarProject->monthlyConsumption(),
                energyRateCopKwh: (float) $solarProject->energy_rate_cop_kwh,
                annualProjectionDays: $solarProject->start_date->daysInYear,
            ),
            $series->days,
        );

        $this->store($solarProject, $estimate, $series->source);

        return $series;
    }

    private function store(SolarProject $solarProject, SolarEstimate $estimate, string $climateSource): void
    {
        DB::transaction(function () use ($solarProject, $estimate, $climateSource): void {
            $solarProject->calculationResult()->updateOrCreate(
                ['solar_project_id' => $solarProject->id],
                [
                    'usable_area_m2' => $estimate->usableAreaM2,
                    'number_of_panels' => $estimate->numberOfPanels,
                    'installed_capacity_kwp' => $estimate->installedCapacityKwp,
                    'estimated_daily_generation_kwh' => $estimate->dailyGenerationKwh,
                    'estimated_monthly_generation_kwh' => $estimate->monthlyGenerationKwh,
                    'estimated_annual_generation_kwh' => $estimate->annualGenerationKwh,
                    'annual_consumption_kwh' => $estimate->annualConsumptionKwh,
                    'coverage_percentage' => $estimate->coveragePercentage,
                    'estimated_annual_savings_cop' => $estimate->annualSavingsCop,
                    'installation_cost_cop' => $estimate->installationCostCop,
                    'payback_period_years' => $estimate->paybackPeriodYears,
                    'climate_source' => $climateSource,
                ],
            );

            $solarProject->monthlyResults()->delete();
            $solarProject->monthlyResults()->createMany(
                array_map(fn (MonthlyGeneration $month) => $month->toArray(), $estimate->months),
            );
        });
    }
}
