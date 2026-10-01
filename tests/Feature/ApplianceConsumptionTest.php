<?php

namespace Tests\Feature;

use App\Models\Municipality;
use App\Models\SolarProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplianceConsumptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_created_with_appliances_stores_them_and_computes_consumption_on_the_server(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('solar-projects.store'), $this->payload([
                'consumption_mode' => 'appliances',
                // A tampered value: the server must ignore it and use the catalog.
                'monthly_consumption_kwh' => 99999,
                'appliances' => [
                    ['key' => 'fridge', 'variant' => 'medium.conventional', 'quantity' => 1, 'hours_per_day' => 24], // 43.2
                    ['key' => 'air_conditioner', 'variant' => '12000.inverter', 'quantity' => 1, 'hours_per_day' => 8], // 192
                    ['key' => 'lighting', 'variant' => 'led', 'quantity' => 6, 'hours_per_day' => 6], // 9.72
                ],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $solarProject = SolarProject::query()->firstOrFail();

        $this->assertEqualsWithDelta(244.92, (float) $solarProject->monthly_consumption_kwh, 0.01);
        $this->assertSame(3, $solarProject->appliances()->count());
        $this->assertDatabaseHas('solar_project_appliances', [
            'solar_project_id' => $solarProject->id,
            'appliance_key' => 'air_conditioner',
            'variant_key' => '12000.inverter',
            'quantity' => 1,
        ]);
    }

    public function test_appliance_mode_requires_at_least_one_appliance(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('solar-projects.store'), $this->payload(['consumption_mode' => 'appliances']))
            ->assertSessionHasErrors('appliances');

        $this->assertDatabaseCount('solar_projects', 0);
    }

    public function test_unknown_variants_are_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('solar-projects.store'), $this->payload([
                'consumption_mode' => 'appliances',
                'appliances' => [['key' => 'fridge', 'variant' => 'gigante.conventional', 'quantity' => 1, 'hours_per_day' => 24]],
            ]))
            ->assertSessionHasErrors('appliances.0.variant');
    }

    public function test_bill_mode_keeps_the_typed_consumption_and_clears_appliances_on_update(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('solar-projects.store'), $this->payload([
            'consumption_mode' => 'appliances',
            'appliances' => [['key' => 'fan', 'variant' => 'stand', 'quantity' => 2, 'hours_per_day' => 8]],
        ]));
        $solarProject = SolarProject::query()->firstOrFail();
        $this->assertSame(1, $solarProject->appliances()->count());

        $this->actingAs($user)
            ->put(route('solar-projects.update', $solarProject), $this->payload([
                'consumption_mode' => 'bill',
                'monthly_consumption_kwh' => 410,
                'end_date' => '2026-01-01',
            ]))
            ->assertSessionHasNoErrors();

        $solarProject->refresh();
        $this->assertSame('410.00', $solarProject->monthly_consumption_kwh);
        $this->assertSame(0, $solarProject->appliances()->count());
    }

    public function test_edit_form_reopens_the_stored_appliances(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('solar-projects.store'), $this->payload([
            'consumption_mode' => 'appliances',
            'appliances' => [['key' => 'beverage_cooler', 'variant' => 'two_doors', 'quantity' => 2, 'hours_per_day' => 24]],
        ]));
        $solarProject = SolarProject::query()->firstOrFail();

        $this->actingAs($user)
            ->get(route('solar-projects.edit', $solarProject))
            ->assertOk()
            ->assertSee('name="consumption_mode" value="appliances"', false)
            ->assertSee('"key":"beverage_cooler","variant":"two_doors","quantity":2', false)
            ->assertSee('href="#appliance-cooler"', false);
    }

    public function test_create_form_offers_the_visual_catalog(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('solar-projects.create'))
            ->assertOk()
            ->assertSee('Con mis equipos')
            ->assertSee('data-add-appliance="air_conditioner"', false)
            ->assertSee('data-add-appliance="fridge"', false)
            ->assertSee('id="appliance-air-conditioner"', false)
            ->assertSee('name="consumption_mode" value="appliances"', false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        $municipality = Municipality::query()->firstOrCreate(
            ['name' => 'Riohacha'],
            ['department' => 'La Guajira', 'zone' => 'Base urbana', 'active' => true],
        );
        $municipality->solarPrices()->firstOrCreate(
            ['location_type' => 'urbana'],
            ['zone_name' => 'Base urbana', 'base_price_per_kw' => 4000000, 'logistic_factor' => 1, 'active' => true],
        );

        return [
            'name' => 'Casa familiar',
            'start_date' => '2026-01-01',
            'energy_rate_cop_kwh' => 900,
            'available_area_m2' => 40,
            'usable_area_percentage' => 80,
            'panel_power_w' => 550,
            'panel_area_m2' => 2.6,
            'system_losses_percentage' => 14,
            'municipality_id' => $municipality->id,
            'location_type' => 'urbana',
            ...$overrides,
        ];
    }
}
