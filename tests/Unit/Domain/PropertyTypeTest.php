<?php

namespace Tests\Unit\Domain;

use App\Domain\Consumption\ApplianceCatalog;
use App\Domain\Property\PropertyType;
use App\Domain\Solar\RequiredPower;
use PHPUnit\Framework\TestCase;

class PropertyTypeTest extends TestCase
{
    public function test_each_kind_of_place_has_its_own_spaces_ending_in_others(): void
    {
        $this->assertSame(['kitchen', 'living', 'bedrooms', 'laundry', 'other'], PropertyType::spaceKeys(PropertyType::HOUSE));
        $this->assertSame(['sales', 'office', 'storage', 'other'], PropertyType::spaceKeys(PropertyType::BUSINESS));
        $this->assertSame(['classrooms', 'offices', 'kitchen', 'common', 'other'], PropertyType::spaceKeys(PropertyType::INSTITUTION));

        foreach (PropertyType::ALL as $type) {
            $this->assertSame(PropertyType::OTHER_SPACE, array_key_last(PropertyType::spaces($type)));
        }
    }

    public function test_an_unknown_space_or_type_falls_back_safely(): void
    {
        $this->assertSame('kitchen', PropertyType::spaceOf(PropertyType::HOUSE, 'kitchen'));
        // A house space shown in a business (e.g. after changing the kind of place) goes to "Otros".
        $this->assertSame('other', PropertyType::spaceOf(PropertyType::BUSINESS, 'bedrooms'));
        $this->assertSame('other', PropertyType::spaceOf(PropertyType::HOUSE, null));
        // Older projects without a kind of place are treated as a house.
        $this->assertSame('Casa', PropertyType::label(null));
        $this->assertSame(PropertyType::HOUSE, PropertyType::normalize('castillo'));
    }

    public function test_the_kind_of_place_decides_which_appliances_come_first(): void
    {
        $this->assertSame([ApplianceCatalog::SEGMENT_HOME], PropertyType::applianceSegments(PropertyType::HOUSE));
        $this->assertSame([ApplianceCatalog::SEGMENT_BUSINESS], PropertyType::applianceSegments(PropertyType::BUSINESS));
        $this->assertSame([ApplianceCatalog::SEGMENT_HOME, ApplianceCatalog::SEGMENT_BUSINESS], PropertyType::applianceSegments(PropertyType::INSTITUTION));
    }

    public function test_it_suggests_a_project_name(): void
    {
        $this->assertSame('Mi casa en Maicao', PropertyType::suggestedProjectName(PropertyType::HOUSE, ' Maicao '));
        $this->assertSame('Mi negocio', PropertyType::suggestedProjectName(PropertyType::BUSINESS, ''));
    }

    public function test_required_power_is_suggested_only_with_consumption(): void
    {
        // 384 kWh / (5.8 HSP × 30 days × 0.86) = 2.57 kW.
        $this->assertSame(2.57, RequiredPower::suggestedKw(384, 14));
        $this->assertNull(RequiredPower::suggestedKw(0, 14));
        $this->assertNull(RequiredPower::suggestedKw(300, 100));
    }

    public function test_the_catalog_describes_the_chosen_options_and_their_drawing(): void
    {
        $catalog = new ApplianceCatalog;

        $this->assertSame('12.000 BTU · Inverter', $catalog->variantLabel('air_conditioner', '12000.inverter'));
        $this->assertSame('', $catalog->variantLabel('router', 'default'));
        $this->assertSame('freezer-upright', $catalog->icon('freezer', 'upright'));
        $this->assertSame('tv', $catalog->icon('tv', '55'));
        $this->assertSame('week', $catalog->usage('washing_machine'));
        $this->assertTrue($catalog->belongsToAnySegment('beverage_cooler', [ApplianceCatalog::SEGMENT_BUSINESS]));
        $this->assertFalse($catalog->belongsToAnySegment('iron', [ApplianceCatalog::SEGMENT_BUSINESS]));
    }
}
