<?php

namespace Tests\Unit\Domain;

use App\Domain\Climate\DailyIrradiance;
use App\Domain\Explanation\ExplainedQuestion;
use App\Domain\Explanation\ProjectExplainer;
use App\Domain\Explanation\ProjectFigures;
use App\Domain\Solar\EnergyProfile;
use App\Domain\Solar\LiveSolarOutput;
use App\Domain\Solar\SolarCalculator;
use App\Domain\Solar\SystemSizing;
use App\Domain\Solar\SystemSpecification;
use PHPUnit\Framework\TestCase;

/**
 * ADR-0014: the system is sized to the consumption, with the roof as the limit.
 */
class SystemSizingTest extends TestCase
{
    public function test_when_the_roof_is_enough_only_the_needed_panels_are_installed(): void
    {
        // 600 kWh / 81 kWh per panel = 7.4 → 8 panels; 14 fit.
        $sizing = SystemSizing::for(600, 81, 14);

        $this->assertSame(8, $sizing->panelsNeeded);
        $this->assertSame(8, $sizing->panelsInstalled);
        $this->assertTrue($sizing->roofIsEnough());
        $this->assertSame(6, $sizing->sparePanels());
        $this->assertSame(0, $sizing->missingPanels());
        $this->assertEqualsWithDelta(108, $sizing->coveragePercentage(), 0.01);
        $this->assertSame(0.0, $sizing->uncoveredMonthlyKwh());
    }

    public function test_when_the_roof_is_short_every_panel_that_fits_is_installed(): void
    {
        // 1.170 kWh / 81 = 14.4 → 15 needed; 11 fit → 891 kWh, 76 %.
        $sizing = SystemSizing::for(1170, 81, 11);

        $this->assertSame(15, $sizing->panelsNeeded);
        $this->assertSame(11, $sizing->panelsInstalled);
        $this->assertFalse($sizing->roofIsEnough());
        $this->assertSame(4, $sizing->missingPanels());
        $this->assertEqualsWithDelta(76.15, $sizing->coveragePercentage(), 0.01);
        $this->assertEqualsWithDelta(279, $sizing->uncoveredMonthlyKwh(), 0.01);
    }

    public function test_an_exact_multiple_does_not_ask_for_an_extra_panel_and_no_consumption_needs_none(): void
    {
        $this->assertSame(5, SystemSizing::for(405, 81, 20)->panelsNeeded);
        $this->assertSame(0, SystemSizing::for(0, 81, 20)->panelsInstalled);
        $this->assertSame(0, SystemSizing::for(500, 81, 0)->panelsInstalled);
    }

    public function test_the_calculator_no_longer_fills_the_whole_roof(): void
    {
        // 40 panels fit (102 m² / 2.5 m²); one panel gives 0.55 kW × 4.8 h × 0.86 × 365 / 12 = 69.1 kWh.
        $estimate = (new SolarCalculator)->estimate(
            new SystemSpecification(availableAreaM2: 120, usableAreaPercentage: 85, panelAreaM2: 2.5, panelPowerW: 550, performanceRatio: 0.86),
            new EnergyProfile(monthlyConsumptionKwh: 500, energyRateCopKwh: 900),
            [new DailyIrradiance('2026-01-10', 200), new DailyIrradiance('2026-01-11', 200)],
        );

        $this->assertSame(40, $estimate->sizing->panelsThatFit);
        $this->assertSame(8, $estimate->sizing->panelsNeeded);
        $this->assertSame(8, $estimate->numberOfPanels);
        $this->assertEqualsWithDelta(4.4, $estimate->installedCapacityKwp, 0.0001);
        $this->assertEqualsWithDelta(69.06, $estimate->sizing->panelMonthlyKwh, 0.01);
        // 4.4 kWp × 5 000 000 instead of the 22 kWp of the full roof.
        $this->assertEqualsWithDelta(22000000, $estimate->installationCostCop, 0.01);
    }

    public function test_the_panels_needed_agree_with_the_coverage_of_the_estimate(): void
    {
        // 11 panels fit and give 2.27 kWh a day each: 749 kWh in 30 days, 760 in an average month
        // (365 / 12 days). The estimate covers 755 kWh (100.6 %): a 12th panel would not be needed.
        $estimate = (new SolarCalculator)->estimate(
            new SystemSpecification(availableAreaM2: 32, usableAreaPercentage: 90, panelAreaM2: 2.5, panelPowerW: 550, performanceRatio: 0.86),
            new EnergyProfile(monthlyConsumptionKwh: 755, energyRateCopKwh: 900),
            [new DailyIrradiance('2026-01-10', 200), new DailyIrradiance('2026-01-11', 200)],
        );

        $this->assertSame(11, $estimate->sizing->panelsNeeded);
        $this->assertTrue($estimate->sizing->roofIsEnough());
        $this->assertGreaterThanOrEqual(100, $estimate->coveragePercentage);
        $this->assertEqualsWithDelta($estimate->monthlyGenerationKwh, $estimate->sizing->monthlyGenerationKwh(), 0.01);
    }

    public function test_live_output_says_which_appliances_the_sun_could_power_now(): void
    {
        // 6 kWp × 800 W/m² × 0.85 = 4.08 kW.
        $live = LiveSolarOutput::for(6, 800, 0.85, [
            ['label' => 'Nevera', 'watts' => 60],
            ['label' => 'Aire acondicionado', 'watts' => 2400],
            ['label' => 'Bomba de agua', 'watts' => 850],
            ['label' => 'Horno', 'watts' => 5000], // Too big for 4.08 kW: skipped.
        ]);

        $this->assertEqualsWithDelta(4.08, $live->outputKw, 0.001);
        $this->assertSame(['Aire acondicionado', 'Bomba de agua', 'Nevera'], $live->poweredAppliances);
        $this->assertSame('Sol fuerte', $live->sunLabel());
        $this->assertTrue(LiveSolarOutput::for(6, 5, 0.85, [])->isNight());
    }

    public function test_the_panels_question_says_when_the_roof_falls_short(): void
    {
        $short = $this->panelsAnswer(panelsNeeded: 15, panelsThatFit: 11, installed: 11);
        $this->assertSame('de los 15 que harían falta', $short->caption);
        $this->assertSame(ExplainedQuestion::TONE_WARNING, $short->tone);
        $this->assertStringContainsString('en tu techo caben 11', $short->paragraphs[2]);

        $enough = $this->panelsAnswer(panelsNeeded: 8, panelsThatFit: 14, installed: 8);
        $this->assertStringContainsString('ni uno más', $enough->paragraphs[2]);
        $this->assertStringContainsString('espacio para 6 más', $enough->paragraphs[2]);
    }

    private function panelsAnswer(int $panelsNeeded, int $panelsThatFit, int $installed): ExplainedQuestion
    {
        $questions = (new ProjectExplainer)->explain(new ProjectFigures(
            calculated: true,
            monthlyConsumptionKwh: 1000,
            energyRateCopKwh: 900,
            installedCapacityKwp: $installed * 0.55,
            numberOfPanels: $installed,
            panelAreaM2: 2.6,
            monthlyGenerationKwh: 800,
            coveragePercentage: 80,
            annualSavingsCop: 8_000_000,
            installationCostCop: 20_000_000,
            paybackYears: 2.5,
            panelsNeeded: $panelsNeeded,
            panelsThatFit: $panelsThatFit,
        ));

        return collect($questions)->firstWhere('key', 'panels');
    }
}
