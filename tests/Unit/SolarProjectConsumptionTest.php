<?php

namespace Tests\Unit;

use App\Models\SolarProject;
use PHPUnit\Framework\TestCase;

class SolarProjectConsumptionTest extends TestCase
{
    public function test_monthly_consumption_is_the_primary_source_for_derived_scales(): void
    {
        $solarProject = new SolarProject([
            'monthly_consumption_kwh' => 900,
            'daily_consumption_kwh' => 20,
            'annual_consumption_kwh' => 8000,
        ]);

        $solarProject->syncConsumptionScales();

        $this->assertSame(900.0, $solarProject->monthlyConsumption());
        $this->assertSame(30.0, $solarProject->dailyConsumption());
        $this->assertSame(10800.0, $solarProject->annualConsumption());
    }

    public function test_legacy_annual_consumption_is_used_as_fallback(): void
    {
        $solarProject = new SolarProject([
            'annual_consumption_kwh' => 12000,
        ]);

        $this->assertEqualsWithDelta(1000.0, $solarProject->monthlyConsumption(), 0.0001);
        $this->assertEqualsWithDelta(33.3333, $solarProject->dailyConsumption(), 0.0001);
        $this->assertEqualsWithDelta(12000.0, $solarProject->annualConsumption(), 0.0001);
    }
}
