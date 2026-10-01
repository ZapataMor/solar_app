<?php

namespace Tests\Unit\Domain;

use App\Domain\Consumption\ApplianceCatalog;
use App\Domain\Consumption\ApplianceLoad;
use App\Domain\Consumption\ConsumptionEstimator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ConsumptionEstimatorTest extends TestCase
{
    public function test_monthly_consumption_is_watts_by_quantity_by_hours_by_thirty_days(): void
    {
        $estimator = new ConsumptionEstimator;

        // 12.000 BTU inverter = 800 W, 2 units, 8 h/day, 30 days = 384 kWh.
        $this->assertEqualsWithDelta(384.0, $estimator->monthlyKwh(new ApplianceLoad('air_conditioner', '12000.inverter', 2, 8)), 0.0001);
    }

    public function test_always_on_appliances_ignore_the_hours_sent(): void
    {
        $estimator = new ConsumptionEstimator;

        // Medium conventional fridge = 60 W × 24 h × 30 days = 43.2 kWh, even if 2 h were sent.
        $this->assertEqualsWithDelta(43.2, $estimator->monthlyKwh(new ApplianceLoad('fridge', 'medium.conventional', 1, 2)), 0.0001);
    }

    public function test_total_adds_every_load(): void
    {
        $total = (new ConsumptionEstimator)->totalMonthlyKwh([
            new ApplianceLoad('fridge', 'medium.conventional', 1, 24),   // 43.2
            new ApplianceLoad('lighting', 'led', 6, 6),                 // 9.72
            new ApplianceLoad('fan', 'stand', 2, 8),                    // 26.4
        ]);

        $this->assertEqualsWithDelta(79.32, $total, 0.0001);
    }

    public function test_hours_are_clamped_to_a_day(): void
    {
        $kwh = (new ConsumptionEstimator)->monthlyKwh(new ApplianceLoad('fan', 'stand', 1, 40));

        $this->assertEqualsWithDelta(55 * 24 * 30 / 1000, $kwh, 0.0001);
    }

    public function test_unknown_variants_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ConsumptionEstimator)->monthlyKwh(new ApplianceLoad('fridge', 'gigante', 1, 24));
    }

    public function test_every_catalog_entry_is_consistent(): void
    {
        $catalog = new ApplianceCatalog;

        foreach ($catalog->all() as $key => $appliance) {
            $this->assertTrue($catalog->hasVariant($key, $appliance['default_variant']), "{$key}: default variant has no watts");
            $this->assertContains($appliance['usage'], ['day', 'week', 'always'], $key);

            // Every combination of choices must have a power value, and nothing else.
            $combinations = [''];
            foreach ($appliance['groups'] as $group) {
                $next = [];
                foreach ($combinations as $prefix) {
                    foreach ($group['choices'] as $choice) {
                        $next[] = $prefix === '' ? $choice['key'] : "{$prefix}.{$choice['key']}";
                    }
                }
                $combinations = $next;
            }
            $expected = $appliance['groups'] === [] ? ['default'] : $combinations;

            $this->assertEqualsCanonicalizing($expected, array_map('strval', array_keys($appliance['watts'])), "{$key}: watts table does not match its option groups");
        }
    }
}
