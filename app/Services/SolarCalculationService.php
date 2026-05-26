<?php

namespace App\Services;

use App\Models\ApiWeatherData;
use App\Models\SolarProject;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

class SolarCalculationService
{
    private const INSTALLATION_COST_PER_KWP_COP = 5000000;
    /**
     * NASA POWER daily ALLSKY_SFC_SW_DWN is stored as W/m2.
     * Multiplying by 24 and dividing by 1000 gives daily HSP in kWh/m2/day.
     */
    public function calculate(
        SolarProject $solarProject,
        ?Collection $weatherData = null,
        string $climateSource = 'nasa_power'
    ): SolarProject
    {
        $solarProject->loadMissing('technicalParameter');

        $technicalParameter = $solarProject->technicalParameter;
        $weatherData ??= $this->apiWeatherDataForProject($solarProject);

        if ($technicalParameter === null) {
            throw new \InvalidArgumentException('El proyecto no tiene parametros tecnicos.');
        }

        if ($weatherData->isEmpty()) {
            throw new \InvalidArgumentException('El proyecto no tiene datos climaticos.');
        }

        $usableArea = $this->calculateUsableArea(
            (float) $technicalParameter->available_area_m2,
            (float) $technicalParameter->usable_area_percentage,
        );
        $numberOfPanels = $this->calculateNumberOfPanels($usableArea, (float) $technicalParameter->panel_area_m2);
        $installedCapacityKwp = $this->calculateInstalledCapacityKwp($numberOfPanels, (float) $technicalParameter->panel_power_w);
        $monthlyResults = $this->calculateMonthlyResults($solarProject, $installedCapacityKwp, $weatherData);

        $measuredGeneration = $monthlyResults->sum('estimated_generation_kwh');
        $weatherDays = max(1, $monthlyResults->sum('days_in_month'));
        $targetAnnualDays = $this->annualProjectionDays($solarProject);
        $estimatedDailyGeneration = $measuredGeneration / $weatherDays;
        $estimatedAnnualGeneration = $estimatedDailyGeneration * $targetAnnualDays;
        $estimatedMonthlyGeneration = $estimatedAnnualGeneration / 12;
        $consumptionScales = $this->consumptionScales($solarProject);
        $estimatedAnnualSavings = $this->calculateSavings($estimatedAnnualGeneration, (float) $solarProject->energy_rate_cop_kwh);
        $installationCost = $this->calculateInstallationCost($installedCapacityKwp);
        $paybackPeriodYears = $this->calculatePaybackPeriodYears($installationCost, $estimatedAnnualSavings);

        DB::transaction(function () use (
            $solarProject,
            $monthlyResults,
            $usableArea,
            $numberOfPanels,
            $installedCapacityKwp,
            $estimatedDailyGeneration,
            $estimatedMonthlyGeneration,
            $estimatedAnnualGeneration,
            $consumptionScales,
            $estimatedAnnualSavings,
            $installationCost,
            $paybackPeriodYears,
            $climateSource,
        ) {
            $solarProject->calculationResult()->updateOrCreate(
                ['solar_project_id' => $solarProject->id],
                [
                    'usable_area_m2' => $usableArea,
                    'number_of_panels' => $numberOfPanels,
                    'installed_capacity_kwp' => $installedCapacityKwp,
                    'estimated_daily_generation_kwh' => $estimatedDailyGeneration,
                    'estimated_monthly_generation_kwh' => $estimatedMonthlyGeneration,
                    'estimated_annual_generation_kwh' => $estimatedAnnualGeneration,
                    'annual_consumption_kwh' => $consumptionScales['annual'],
                    'coverage_percentage' => $this->calculateCoveragePercentage($estimatedAnnualGeneration, $consumptionScales['annual']),
                    'estimated_annual_savings_cop' => $estimatedAnnualSavings,
                    'installation_cost_cop' => $installationCost,
                    'payback_period_years' => $paybackPeriodYears,
                    'climate_source' => $climateSource,
                ],
            );

            $solarProject->monthlyResults()->delete();

            $monthlyResults->each(fn (array $result) => $solarProject->monthlyResults()->create($result));
        });

        return $solarProject->fresh(['calculationResult', 'monthlyResults']);
    }

    public function calculateUsableArea(float $availableAreaM2, float $usableAreaPercentage): float
    {
        return $availableAreaM2 * $usableAreaPercentage / 100;
    }

    public function calculateNumberOfPanels(float $usableAreaM2, float $panelAreaM2): int
    {
        if ($panelAreaM2 <= 0) {
            return 0;
        }

        return (int) floor($usableAreaM2 / $panelAreaM2);
    }

    public function calculateInstalledCapacityKwp(int $numberOfPanels, float $panelPowerW): float
    {
        return $numberOfPanels * $panelPowerW / 1000;
    }

    public function calculateConsumptionMonthly(float $annualConsumptionKwh): float
    {
        return $annualConsumptionKwh / 12;
    }

    public function calculateConsumptionDaily(float $monthlyConsumptionKwh): float
    {
        return $monthlyConsumptionKwh / 30;
    }

    public function calculateConsumptionAnnual(float $monthlyConsumptionKwh): float
    {
        return $monthlyConsumptionKwh * 12;
    }

    /**
     * @return array{monthly: float, daily: float, annual: float}
     */
    public function consumptionScales(SolarProject $solarProject): array
    {
        $monthlyConsumption = $solarProject->monthlyConsumption();

        return [
            'monthly' => $monthlyConsumption,
            'daily' => $this->calculateConsumptionDaily($monthlyConsumption),
            'annual' => $this->calculateConsumptionAnnual($monthlyConsumption),
        ];
    }

    public function calculateSavings(float $generatedEnergyKwh, float $energyRateCopKwh): float
    {
        return $generatedEnergyKwh * $energyRateCopKwh;
    }

    public function calculateInstallationCost(float $installedCapacityKwp): float
    {
        return $installedCapacityKwp * self::INSTALLATION_COST_PER_KWP_COP;
    }

    public function calculatePaybackPeriodYears(float $installationCostCop, float $annualSavingsCop): ?float
    {
        if ($annualSavingsCop <= 0) {
            return null;
        }

        return $installationCostCop / $annualSavingsCop;
    }

    public function calculateCoveragePercentage(float $generationKwh, float $consumptionKwh): float
    {
        if ($consumptionKwh <= 0) {
            return 0;
        }

        return $generationKwh / $consumptionKwh * 100;
    }

    /**
     * @param  Collection<int, object>  $weatherData
     * @return Collection<int, array<string, float|int|string>>
     */
    private function calculateMonthlyResults(SolarProject $solarProject, float $installedCapacityKwp, Collection $weatherData): Collection
    {
        $technicalParameter = $solarProject->technicalParameter;
        $performanceRatio = (float) $technicalParameter->performance_ratio;
        $monthlyConsumption = $solarProject->monthlyConsumption();
        $energyRate = (float) $solarProject->energy_rate_cop_kwh;

        return $weatherData
            ->filter(fn ($weatherData) => $weatherData->allsky_sfc_sw_dwn !== null)
            ->groupBy(fn ($weatherData) => $weatherData->date_time->format('n'))
            ->sortKeys()
            ->map(function (Collection $monthData, int|string $monthNumber) use (
                $installedCapacityKwp,
                $performanceRatio,
                $monthlyConsumption,
                $energyRate,
            ) {
                $dailyRadiation = $monthData
                    ->groupBy(fn ($weatherData) => $weatherData->date_time->toDateString())
                    ->map(fn (Collection $dayData) => $dayData->average(fn ($weatherData) => (float) $weatherData->allsky_sfc_sw_dwn) * 24 / 1000);

                $daysInMonth = $dailyRadiation->count();
                $averageDailyRadiation = $daysInMonth > 0 ? $dailyRadiation->average() : 0;
                $estimatedGeneration = $installedCapacityKwp * $averageDailyRadiation * $performanceRatio * $daysInMonth;

                return [
                    'month_number' => (int) $monthNumber,
                    'month_name' => $this->monthName((int) $monthNumber),
                    'days_in_month' => $daysInMonth,
                    'average_daily_solar_radiation' => $averageDailyRadiation,
                    'estimated_generation_kwh' => $estimatedGeneration,
                    'estimated_consumption_kwh' => $monthlyConsumption,
                    'coverage_percentage' => $this->calculateCoveragePercentage($estimatedGeneration, $monthlyConsumption),
                    'estimated_savings_cop' => $this->calculateSavings($estimatedGeneration, $energyRate),
                ];
            })
            ->values();
    }

    /**
     * @param  iterable<array{date_time: Carbon|string, allsky_sfc_sw_dwn: mixed, t2m?: mixed, rh2m?: mixed, prectotcorr?: mixed, ws10m?: mixed}>  $rows
     * @return Collection<int, object>
     */
    public function weatherDataFromRows(iterable $rows): Collection
    {
        return collect($rows)->map(function (array $row): object {
            $weatherData = new stdClass;
            $weatherData->date_time = $row['date_time'] instanceof Carbon
                ? $row['date_time']
                : Carbon::parse($row['date_time']);
            $weatherData->allsky_sfc_sw_dwn = $row['allsky_sfc_sw_dwn'] !== null ? (float) $row['allsky_sfc_sw_dwn'] : null;
            $weatherData->t2m = ($row['t2m'] ?? null) !== null ? (float) $row['t2m'] : null;
            $weatherData->rh2m = ($row['rh2m'] ?? null) !== null ? (float) $row['rh2m'] : null;
            $weatherData->prectotcorr = ($row['prectotcorr'] ?? null) !== null ? (float) $row['prectotcorr'] : null;
            $weatherData->ws10m = ($row['ws10m'] ?? null) !== null ? (float) $row['ws10m'] : null;

            return $weatherData;
        });
    }

    private function apiWeatherDataForProject(SolarProject $solarProject): Collection
    {
        return ApiWeatherData::query()
            ->whereBetween('date_time', [
                $solarProject->start_date->copy()->startOfDay(),
                $solarProject->end_date->copy()->endOfDay(),
            ])
            ->orderBy('date_time')
            ->get();
    }

    private function monthName(int $monthNumber): string
    {
        return now()
            ->month($monthNumber)
            ->locale('es')
            ->translatedFormat('F');
    }

    private function annualProjectionDays(SolarProject $solarProject): int
    {
        $startDate = $solarProject->start_date;

        if ($startDate instanceof Carbon) {
            return $startDate->daysInYear;
        }

        return now()->daysInYear;
    }
}
