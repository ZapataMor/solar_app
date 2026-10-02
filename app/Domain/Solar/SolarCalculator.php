<?php

namespace App\Domain\Solar;

use App\Domain\Climate\DailyIrradiance;
use InvalidArgumentException;

/**
 * Pure sizing engine: system + consumption + irradiance → generation, savings and payback.
 *
 * It has no framework, database or HTTP dependency, so it can be unit tested
 * with plain values and reused by any entry point (web, API, console).
 */
final class SolarCalculator
{
    public const INSTALLATION_COST_PER_KWP_COP = 5000000;

    private const MONTH_NAMES = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
        5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
        9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
    ];

    /**
     * @param  iterable<DailyIrradiance>  $irradiance
     */
    public function estimate(SystemSpecification $system, EnergyProfile $energy, iterable $irradiance): SolarEstimate
    {
        $peakSunHoursByMonth = $this->peakSunHoursByMonth($irradiance);

        if ($peakSunHoursByMonth === []) {
            throw new InvalidArgumentException('El proyecto no tiene datos climaticos.');
        }

        // Sized to the consumption, with the roof as the limit (ADR-0014): no more panels than needed.
        $usableArea = $this->usableArea($system->availableAreaM2, $system->usableAreaPercentage);
        $sizing = SystemSizing::for(
            $energy->monthlyConsumptionKwh,
            $this->panelMonthlyGenerationKwh($system, $this->averagePeakSunHours($peakSunHoursByMonth), $energy->annualProjectionDays),
            $this->numberOfPanels($usableArea, $system->panelAreaM2),
        );
        $numberOfPanels = $sizing->panelsInstalled;
        $installedCapacityKwp = $this->installedCapacityKwp($numberOfPanels, $system->panelPowerW);

        $months = [];
        foreach ($peakSunHoursByMonth as $monthNumber => $dailyPeakSunHours) {
            $days = count($dailyPeakSunHours);
            $averagePeakSunHours = array_sum($dailyPeakSunHours) / $days;
            $generation = $installedCapacityKwp * $averagePeakSunHours * $system->performanceRatio * $days;

            $months[] = new MonthlyGeneration(
                monthNumber: $monthNumber,
                monthName: self::MONTH_NAMES[$monthNumber],
                days: $days,
                averageDailyPeakSunHours: $averagePeakSunHours,
                generationKwh: $generation,
                consumptionKwh: $energy->monthlyConsumptionKwh,
                coveragePercentage: $this->coveragePercentage($generation, $energy->monthlyConsumptionKwh),
                // Only the energy you stop buying saves money: a surplus does not lower the bill.
                savingsCop: $this->savings(min($generation, $energy->monthlyConsumptionKwh / 30 * $days), $energy->energyRateCopKwh),
            );
        }

        $measuredGeneration = array_sum(array_map(fn (MonthlyGeneration $month) => $month->generationKwh, $months));
        $measuredDays = max(1, array_sum(array_map(fn (MonthlyGeneration $month) => $month->days, $months)));
        $dailyGeneration = $measuredGeneration / $measuredDays;
        $annualGeneration = $dailyGeneration * $energy->annualProjectionDays;
        $annualConsumption = $energy->annualConsumptionKwh();
        $annualSavings = $this->savings(min($annualGeneration, $annualConsumption), $energy->energyRateCopKwh);
        $installationCost = $this->installationCost($installedCapacityKwp);

        return new SolarEstimate(
            usableAreaM2: $usableArea,
            numberOfPanels: $numberOfPanels,
            installedCapacityKwp: $installedCapacityKwp,
            dailyGenerationKwh: $dailyGeneration,
            monthlyGenerationKwh: $annualGeneration / 12,
            annualGenerationKwh: $annualGeneration,
            annualConsumptionKwh: $annualConsumption,
            coveragePercentage: $this->coveragePercentage($annualGeneration, $annualConsumption),
            annualSavingsCop: $annualSavings,
            installationCostCop: $installationCost,
            paybackPeriodYears: $this->paybackPeriodYears($installationCost, $annualSavings),
            months: $months,
            sizing: $sizing,
        );
    }

    /**
     * What one panel produces in an average month with this sun (kWh).
     *
     * The month is a twelfth of the year, like the estimate's monthly generation and coverage:
     * a 30-day month would ask for one more panel when the estimate already covers 100 %.
     */
    public function panelMonthlyGenerationKwh(SystemSpecification $system, float $averageDailyPeakSunHours, int $daysPerYear = 365): float
    {
        return $system->panelPowerW / 1000 * $averageDailyPeakSunHours * $system->performanceRatio * $daysPerYear / 12;
    }

    /**
     * @param  array<int, list<float>>  $peakSunHoursByMonth
     */
    private function averagePeakSunHours(array $peakSunHoursByMonth): float
    {
        $days = array_merge(...array_values($peakSunHoursByMonth));

        return $days === [] ? 0.0 : array_sum($days) / count($days);
    }

    public function usableArea(float $availableAreaM2, float $usableAreaPercentage): float
    {
        return $availableAreaM2 * $usableAreaPercentage / 100;
    }

    public function numberOfPanels(float $usableAreaM2, float $panelAreaM2): int
    {
        if ($panelAreaM2 <= 0) {
            return 0;
        }

        return (int) floor($usableAreaM2 / $panelAreaM2);
    }

    public function installedCapacityKwp(int $numberOfPanels, float $panelPowerW): float
    {
        return $numberOfPanels * $panelPowerW / 1000;
    }

    public function savings(float $generatedEnergyKwh, float $energyRateCopKwh): float
    {
        return $generatedEnergyKwh * $energyRateCopKwh;
    }

    public function installationCost(float $installedCapacityKwp): float
    {
        return $installedCapacityKwp * self::INSTALLATION_COST_PER_KWP_COP;
    }

    public function paybackPeriodYears(float $installationCostCop, float $annualSavingsCop): ?float
    {
        if ($annualSavingsCop <= 0) {
            return null;
        }

        return $installationCostCop / $annualSavingsCop;
    }

    public function coveragePercentage(float $generationKwh, float $consumptionKwh): float
    {
        if ($consumptionKwh <= 0) {
            return 0;
        }

        return $generationKwh / $consumptionKwh * 100;
    }

    /**
     * Averages repeated readings of the same day, then groups the daily peak
     * sun hours by calendar month (ascending).
     *
     * @param  iterable<DailyIrradiance>  $irradiance
     * @return array<int, list<float>>
     */
    private function peakSunHoursByMonth(iterable $irradiance): array
    {
        $readingsByDate = [];
        foreach ($irradiance as $day) {
            $readingsByDate[$day->date][] = $day;
        }

        $byMonth = [];
        foreach ($readingsByDate as $readings) {
            $average = array_sum(array_map(fn (DailyIrradiance $day) => $day->peakSunHours(), $readings)) / count($readings);
            $byMonth[$readings[0]->month()][] = $average;
        }

        ksort($byMonth);

        return $byMonth;
    }
}
