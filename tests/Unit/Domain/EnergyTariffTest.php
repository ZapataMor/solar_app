<?php

namespace Tests\Unit\Domain;

use App\Domain\Reference\EnergyTariff;
use App\Domain\Reference\ReferenceValueCatalog;
use PHPUnit\Framework\TestCase;

/**
 * ADR-0015: the kWh price of each kind of place from the reference values.
 */
class EnergyTariffTest extends TestCase
{
    public function test_a_business_pays_the_contribution_and_the_others_the_tariff(): void
    {
        $this->assertEqualsWithDelta(1068, EnergyTariff::forPropertyType('business', 890, 20), 0.001);
        $this->assertSame(890.0, EnergyTariff::forPropertyType('house', 890, 20));
        $this->assertSame(890.0, EnergyTariff::forPropertyType('institution', 890, 20));
        // Older projects without a kind of place count as a house.
        $this->assertSame(890.0, EnergyTariff::forPropertyType(null, 890, 20));
    }

    public function test_the_catalog_knows_its_values_and_their_defaults(): void
    {
        $this->assertTrue(ReferenceValueCatalog::has(ReferenceValueCatalog::ENERGY_RATE));
        $this->assertFalse(ReferenceValueCatalog::has('gold_price'));
        $this->assertSame(890.0, ReferenceValueCatalog::definition(ReferenceValueCatalog::ENERGY_RATE)->default);
        $this->assertSame('%', ReferenceValueCatalog::definition(ReferenceValueCatalog::COMMERCIAL_CONTRIBUTION)->unit);
    }
}
