<?php

namespace Tests\Unit\Domain;

use App\Domain\Climate\DailyIrradiance;
use App\Domain\Solar\EnergyProfile;
use App\Domain\Solar\SolarCalculator;
use App\Domain\Solar\SystemSpecification;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SolarCalculatorTest extends TestCase
{
    public function test_it_sizes_the_system_and_projects_generation(): void
    {
        $estimate = (new SolarCalculator)->estimate(
            $this->system(),
            new EnergyProfile(monthlyConsumptionKwh: 24500 / 12, energyRateCopKwh: 820, annualProjectionDays: 365),
            [new DailyIrradiance('2017-01-01', 83.333333), new DailyIrradiance('2017-01-02', 100)],
        );

        $this->assertEqualsWithDelta(102, $estimate->usableAreaM2, 0.0001);
        $this->assertSame(40, $estimate->numberOfPanels);
        $this->assertEqualsWithDelta(22, $estimate->installedCapacityKwp, 0.0001);
        $this->assertEqualsWithDelta(41.624, $estimate->dailyGenerationKwh, 0.001);
        $this->assertEqualsWithDelta(15192.76, $estimate->annualGenerationKwh, 0.1);
        $this->assertEqualsWithDelta(24500, $estimate->annualConsumptionKwh, 0.1);
        $this->assertEqualsWithDelta(12458061.46, $estimate->annualSavingsCop, 5);
        $this->assertEqualsWithDelta(110000000, $estimate->installationCostCop, 0.01);

        $this->assertCount(1, $estimate->months);
        $this->assertSame('enero', $estimate->months[0]->monthName);
        $this->assertSame(2, $estimate->months[0]->days);
        $this->assertEqualsWithDelta(2.2, $estimate->months[0]->averageDailyPeakSunHours, 0.0001);
        $this->assertEqualsWithDelta(83.248, $estimate->months[0]->generationKwh, 0.001);
    }

    public function test_repeated_readings_of_a_day_are_averaged_and_months_are_sorted(): void
    {
        $estimate = (new SolarCalculator)->estimate($this->system(), new EnergyProfile(1000, 800), [
            new DailyIrradiance('2026-03-05', 200),
            new DailyIrradiance('2026-01-10', 100),
            new DailyIrradiance('2026-01-10', 300),
        ]);

        $this->assertSame([1, 3], array_map(fn ($month) => $month->monthNumber, $estimate->months));
        $this->assertSame(1, $estimate->months[0]->days);
        $this->assertEqualsWithDelta(4.8, $estimate->months[0]->averageDailyPeakSunHours, 0.0001);
    }

    public function test_it_rejects_an_empty_climate_series(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new SolarCalculator)->estimate($this->system(), new EnergyProfile(1000, 800), []);
    }

    public function test_edge_cases_do_not_divide_by_zero(): void
    {
        $calculator = new SolarCalculator;

        $this->assertSame(0, $calculator->numberOfPanels(100, 0));
        $this->assertSame(0.0, (float) $calculator->coveragePercentage(500, 0));
        $this->assertNull($calculator->paybackPeriodYears(1000000, 0));
    }

    private function system(): SystemSpecification
    {
        return new SystemSpecification(
            availableAreaM2: 120,
            usableAreaPercentage: 85,
            panelAreaM2: 2.5,
            panelPowerW: 550,
            performanceRatio: 0.86,
        );
    }
}
