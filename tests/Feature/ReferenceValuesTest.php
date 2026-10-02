<?php

namespace Tests\Feature;

use App\Actions\SolarProjects\CheckCalculationFreshness;
use App\Models\Municipality;
use App\Models\ReferenceValue;
use App\Models\SolarProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ADR-0015: general values of the system that an administrator keeps up to date.
 */
class ReferenceValuesTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_administrators_see_and_change_the_reference_values(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = User::factory()->create();

        $this->actingAs($admin)
            ->get(route('reference-values.index'))
            ->assertOk()
            ->assertSee('Valores de referencia')
            ->assertSee('Tarifa del kWh')
            ->assertSee('Contribución de los negocios')
            // Nothing recorded yet: the catalog default applies.
            ->assertSee('Valor por defecto del sistema')
            ->assertSee('$890')
            ->assertSee('Un negocio paga $1.068 con la contribución');

        $this->actingAs($client)->get(route('reference-values.index'))->assertForbidden();
        $this->actingAs($client)->post(route('reference-values.store'), ['key' => 'energy_rate_cop_kwh', 'value' => 1, 'valid_from' => '2026-01-01'])->assertForbidden();
        $this->actingAs($client)->get(route('solar-projects.index'))->assertDontSee(route('reference-values.index'));
        $this->actingAs($admin)->get(route('solar-projects.index'))->assertSee(route('reference-values.index'));
    }

    public function test_a_new_tariff_applies_to_projects_without_their_own_and_asks_to_recalculate(): void
    {
        $this->travelTo('2026-10-02 09:00');
        $admin = User::factory()->create(['role' => 'admin']);
        $house = $this->calculatedProject('house', null);
        $shop = $this->calculatedProject('business', null);
        $ownTariff = $this->calculatedProject('house', 950);

        $this->assertEquals(890, $house->energy_rate_cop_kwh);
        $this->assertFalse(app(CheckCalculationFreshness::class)($house)->needsRecalculation());

        $this->travel(1)->hours();
        $this->actingAs($admin)
            ->post(route('reference-values.store'), [
                'key' => 'energy_rate_cop_kwh',
                'value' => 920,
                'valid_from' => '2026-10-01',
                'source' => 'Tarifas Air-e octubre',
            ])
            ->assertRedirect(route('reference-values.index').'#valor-energy_rate_cop_kwh')
            ->assertSessionHas('status', 'Se guardó Tarifa del kWh: ya rige.');

        $this->assertEquals(920, $house->fresh()->energy_rate_cop_kwh);
        $this->assertEquals(1104, $shop->fresh()->energy_rate_cop_kwh);
        $this->assertEquals(950, $ownTariff->fresh()->energy_rate_cop_kwh);

        $freshness = app(CheckCalculationFreshness::class);
        $this->assertSame(['Se actualizó la tarifa de referencia del kWh.'], $freshness($house->fresh())->reasons);
        $this->assertFalse($freshness($ownTariff->fresh())->needsRecalculation());

        $this->actingAs($admin)
            ->get(route('reference-values.index'))
            ->assertSee('$920')
            ->assertSee('Rige desde el 1 de octubre de 2026')
            ->assertSee('Tarifas Air-e octubre')
            ->assertSee('Lo usan 2 proyectos sin tarifa propia');
    }

    public function test_a_value_with_a_future_date_waits_for_it_and_keeps_the_history(): void
    {
        $this->travelTo('2026-10-02 09:00');
        $admin = User::factory()->create(['role' => 'admin']);
        $house = $this->calculatedProject('house', null);
        ReferenceValue::query()->create(['key' => 'energy_rate_cop_kwh', 'value' => 890, 'valid_from' => '2026-08-01']);

        $this->actingAs($admin)
            ->post(route('reference-values.store'), ['key' => 'energy_rate_cop_kwh', 'value' => 940, 'valid_from' => '2026-11-01'])
            ->assertSessionHas('status', 'Se guardó Tarifa del kWh: regirá desde el 1 de noviembre de 2026.');

        $this->assertEquals(890, $house->fresh()->energy_rate_cop_kwh);
        $this->actingAs($admin)
            ->get(route('reference-values.index'))
            ->assertSee('Programado:')
            ->assertSee('desde el 1 de noviembre de 2026');

        $this->travelTo('2026-11-01 08:00');
        $this->assertEquals(940, SolarProject::find($house->id)->energy_rate_cop_kwh);
        $this->assertSame(2, ReferenceValue::query()->where('key', 'energy_rate_cop_kwh')->count());
    }

    public function test_values_out_of_range_or_unknown_are_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('reference-values.store'), ['key' => 'commercial_contribution_percentage', 'value' => 150, 'valid_from' => '2026-10-01'])
            ->assertSessionHasErrors(['value' => 'El valor debe estar entre 0 y 100.']);
        $this->actingAs($admin)
            ->post(route('reference-values.store'), ['key' => 'gold_price', 'value' => 1, 'valid_from' => '2026-10-01'])
            ->assertSessionHasErrors('key');

        $this->assertSame(0, ReferenceValue::query()->count());
    }

    public function test_the_client_may_leave_the_tariff_empty_and_go_back_to_the_reference_one(): void
    {
        $user = User::factory()->create();
        $municipality = Municipality::query()->create(['name' => 'Maicao', 'department' => 'La Guajira', 'zone' => 'Media Guajira', 'active' => true]);
        $municipality->solarPrices()->create(['zone_name' => 'Base urbana', 'location_type' => 'urbana', 'base_price_per_kw' => 4000000, 'logistic_factor' => 1, 'active' => true]);
        $form = [
            'property_type' => 'business',
            'name' => 'Mi negocio en Maicao',
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
            'available_area_m2' => 50,
            'usable_area_percentage' => 75,
            'panel_power_w' => 550,
            'panel_area_m2' => 2.58,
            'system_losses_percentage' => 14,
            'municipality_id' => $municipality->id,
            'location_type' => 'urbana',
        ];

        $this->actingAs($user)
            ->get(route('solar-projects.create'))
            ->assertSee('Si la dejas vacía usamos la tarifa de Air-e: $890 por kWh')
            ->assertSee('($1.068 para negocios, con la contribución)', false);

        $this->actingAs($user)->post(route('solar-projects.store'), $form)->assertSessionHasNoErrors();
        $solarProject = SolarProject::query()->firstOrFail();
        $this->assertTrue($solarProject->usesReferenceEnergyRate());
        $this->assertEquals(1068, $solarProject->energy_rate_cop_kwh);

        // Writing the one in the bill makes it the project's own; clearing it goes back to the reference.
        $this->actingAs($user)->put(route('solar-projects.update', $solarProject), [...$form, 'energy_rate_cop_kwh' => 1010]);
        $this->assertEquals(1010, $solarProject->fresh()->energy_rate_cop_kwh);
        $this->assertMatchesRegularExpression('/<input[^>]*name="energy_rate_cop_kwh"[^>]*value="1010"/s', $this->actingAs($user)->get(route('solar-projects.edit', $solarProject))->getContent());

        $this->actingAs($user)->put(route('solar-projects.update', $solarProject), [...$form, 'energy_rate_cop_kwh' => '']);
        $this->assertTrue($solarProject->fresh()->usesReferenceEnergyRate());
        $this->assertMatchesRegularExpression('/<input[^>]*name="energy_rate_cop_kwh"[^>]*value=""/s', $this->actingAs($user)->get(route('solar-projects.edit', $solarProject))->getContent());
    }

    /**
     * A project calculated an hour ago, with nothing changed since.
     */
    private function calculatedProject(string $propertyType, ?float $ownTariff): SolarProject
    {
        $solarProject = User::factory()->create()->solarProjects()->create([
            'name' => 'Proyecto '.$propertyType,
            'property_type' => $propertyType,
            'location_name' => SolarProject::LOCATION_NAME,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'monthly_consumption_kwh' => 300,
            'energy_rate_cop_kwh' => $ownTariff,
        ]);
        $solarProject->technicalParameter()->create([
            'available_area_m2' => 40, 'usable_area_percentage' => 80, 'panel_power_w' => 550,
            'panel_area_m2' => 2.6, 'performance_ratio' => 0.86, 'system_losses_percentage' => 14,
        ]);
        $solarProject->calculationResult()->create(['coverage_percentage' => 100, 'panels_needed' => 4, 'climate_source' => 'nasa_power']);
        SolarProject::query()->whereKey($solarProject->id)->update(['updated_at' => now()->subHours(2)]);
        $solarProject->technicalParameter()->update(['updated_at' => now()->subHours(2)]);
        $solarProject->calculationResult()->update(['updated_at' => now()->subHour()]);

        return $solarProject->fresh();
    }
}
