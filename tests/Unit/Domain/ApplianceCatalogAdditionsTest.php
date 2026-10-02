<?php

namespace Tests\Unit\Domain;

use App\Domain\Consumption\ApplianceCatalog;
use PHPUnit\Framework\TestCase;

/**
 * ADR-0017: the built-in catalog plus the appliances administrators add.
 */
class ApplianceCatalogAdditionsTest extends TestCase
{
    public function test_added_appliances_join_the_catalog_without_replacing_built_in_ones(): void
    {
        $catalog = new ApplianceCatalog([
            'toaster' => $this->entry('Tostadora', ['default' => 850], usage: 'week', hours: 2),
            'fridge' => $this->entry('Nevera falsa', ['default' => 1]),
            'old_heater' => [...$this->entry('Calentador', ['default' => 1500]), 'active' => false],
        ]);

        $this->assertSame('Tostadora', $catalog->label('toaster'));
        $this->assertSame('Nevera', $catalog->label('fridge'));
        $this->assertTrue($catalog->isBuiltIn('fridge'));
        $this->assertFalse($catalog->isBuiltIn('toaster'));

        // Hidden: not offered, but still known for the diary rows that use it.
        $this->assertArrayNotHasKey('old_heater', $catalog->offered());
        $this->assertTrue($catalog->hasVariant('old_heater', 'default'));
    }

    public function test_typical_consumption_follows_the_kind_of_use(): void
    {
        $catalog = new ApplianceCatalog([
            'toaster' => $this->entry('Tostadora', ['default' => 850], usage: 'week', hours: 2),
        ]);

        // 2 h a week: 850 W × 2/7 h × 30 = 7,29 kWh.
        $this->assertEqualsWithDelta(7.29, $catalog->typicalMonthlyKwh('toaster'), 0.01);
        // Always on: a medium conventional fridge, 60 W × 24 h × 30 = 43,2 kWh.
        $this->assertEqualsWithDelta(43.2, $catalog->typicalMonthlyKwh('fridge'), 0.001);
        // Another option of the same appliance: 12.000 BTU inverter, 800 W × 8 h × 30.
        $this->assertEqualsWithDelta(192, $catalog->typicalMonthlyKwh('air_conditioner', '12000.inverter'), 0.001);
        $this->assertSame([600.0, 2400.0], [min($catalog->variantWatts('air_conditioner')), max($catalog->variantWatts('air_conditioner'))]);
    }

    /**
     * @param  array<string, float>  $watts
     * @return array<string, mixed>
     */
    private function entry(string $label, array $watts, string $usage = 'day', float $hours = 4): array
    {
        return [
            'label' => $label, 'icon' => 'plug', 'segments' => ['home'], 'usage' => $usage,
            'default_hours' => $hours, 'default_quantity' => 1, 'hint' => null,
            'groups' => [], 'default_variant' => 'default', 'watts' => $watts,
        ];
    }
}
