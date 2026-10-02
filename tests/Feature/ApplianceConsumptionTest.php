<?php

namespace Tests\Feature;

use App\Actions\SolarProjects\CheckCalculationFreshness;
use App\Models\Municipality;
use App\Models\SolarProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Consumption diary (ADR-0013): appliances added by space after creating the project.
 */
class ApplianceConsumptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_adding_appliances_computes_the_consumption_on_the_server(): void
    {
        [$user, $solarProject] = $this->project();

        $this->actingAs($user)
            ->post(route('solar-projects.appliances.store', $solarProject), [
                'space' => 'kitchen',
                'key' => 'fridge',
                'variant' => 'medium.conventional',
                'quantity' => 1,
                'hours_per_day' => 3, // Always-on: the server runs it 24 h whatever was sent.
                'monthly_consumption_kwh' => 99999, // A tampered value: ignored.
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('solar-projects.consumption', $solarProject).'#espacio-kitchen')
            ->assertSessionHas('status', 'Se agregó Nevera a Cocina.');

        $this->actingAs($user)->post(route('solar-projects.appliances.store', $solarProject), [
            'space' => 'bedrooms', 'key' => 'air_conditioner', 'variant' => '12000.inverter', 'quantity' => 1, 'hours_per_day' => 8,
        ]);

        $solarProject->refresh();
        // Fridge 60 W × 24 h × 30 = 43.2 kWh; AC 800 W × 8 h × 30 = 192 kWh.
        $this->assertEqualsWithDelta(235.2, (float) $solarProject->monthly_consumption_kwh, 0.01);
        $this->assertEqualsWithDelta(235.2 * 12, (float) $solarProject->annual_consumption_kwh, 0.01);
        $this->assertDatabaseHas('solar_project_appliances', [
            'solar_project_id' => $solarProject->id,
            'space' => 'kitchen',
            'appliance_key' => 'fridge',
            'hours_per_day' => 24,
        ]);
    }

    public function test_changing_an_appliance_updates_the_consumption_and_can_move_it_to_another_space(): void
    {
        [$user, $solarProject] = $this->project();
        $appliance = $solarProject->appliances()->create(['space' => 'living', 'appliance_key' => 'fan', 'variant_key' => 'stand', 'quantity' => 1, 'hours_per_day' => 8]);

        $this->actingAs($user)
            ->put(route('solar-projects.appliances.update', [$solarProject, $appliance]), [
                'space' => 'bedrooms', 'key' => 'fan', 'variant' => 'ceiling', 'quantity' => 2, 'hours_per_day' => 10,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('solar-projects.consumption', $solarProject).'#espacio-bedrooms');

        $this->assertDatabaseHas('solar_project_appliances', ['id' => $appliance->id, 'space' => 'bedrooms', 'variant_key' => 'ceiling', 'quantity' => 2]);
        // 2 × 65 W × 10 h × 30 = 39 kWh.
        $this->assertEqualsWithDelta(39, (float) $solarProject->fresh()->monthly_consumption_kwh, 0.01);
    }

    public function test_removing_the_last_appliance_leaves_the_project_without_consumption(): void
    {
        [$user, $solarProject] = $this->project();
        $this->actingAs($user)->post(route('solar-projects.appliances.store', $solarProject), [
            'space' => 'kitchen', 'key' => 'fridge', 'variant' => 'small.inverter', 'quantity' => 1, 'hours_per_day' => 24,
        ]);
        $appliance = $solarProject->appliances()->firstOrFail();

        $this->actingAs($user)
            ->delete(route('solar-projects.appliances.destroy', [$solarProject, $appliance]))
            ->assertRedirect(route('solar-projects.consumption', $solarProject).'#espacio-kitchen');

        $solarProject->refresh();
        $this->assertSame(0, $solarProject->appliances()->count());
        // The annual value must not bring the old consumption back.
        $this->assertEquals(0, $solarProject->monthly_consumption_kwh);
        $this->assertEquals(0, $solarProject->annual_consumption_kwh);
        $this->assertNull($solarProject->required_power_kw);
    }

    public function test_diary_changes_mark_the_calculation_to_be_redone(): void
    {
        [$user, $solarProject] = $this->project();
        $checkFreshness = app(CheckCalculationFreshness::class);

        // No appliances: nothing to calculate yet, and it says why.
        $freshness = $checkFreshness($solarProject->fresh());
        $this->assertSame('not_ready', $freshness->status);
        $this->assertSame(['Agrega tus equipos en la pestaña Consumo para calcular.'], $freshness->reasons);

        // A project calculated an hour ago…
        $solarProject->update(['monthly_consumption_kwh' => 100]);
        $solarProject->calculationResult()->create(['coverage_percentage' => 80, 'panels_needed' => 2, 'climate_source' => 'nasa_power']);
        SolarProject::query()->whereKey($solarProject->id)->update(['updated_at' => now()->subHours(2)]);
        $solarProject->technicalParameter()->update(['updated_at' => now()->subHours(2)]);
        $solarProject->calculationResult()->update(['updated_at' => now()->subHour()]);
        $this->assertFalse($checkFreshness($solarProject->fresh())->needsRecalculation());

        // …gets the "!" as soon as its diary changes.
        $this->actingAs($user)->post(route('solar-projects.appliances.store', $solarProject), [
            'space' => 'living', 'key' => 'tv', 'variant' => '43', 'quantity' => 1, 'hours_per_day' => 5,
        ]);

        $this->assertSame('stale', $checkFreshness($solarProject->fresh())->status);
    }

    public function test_appliances_and_spaces_must_exist(): void
    {
        [$user, $solarProject] = $this->project();

        $this->actingAs($user)
            ->post(route('solar-projects.appliances.store', $solarProject), [
                'space' => 'kitchen', 'key' => 'fridge', 'variant' => 'gigante.conventional', 'quantity' => 1, 'hours_per_day' => 24,
            ])
            ->assertSessionHasErrors(['variant' => 'Elige una opción válida para ese equipo.']);

        // "Aulas" is a space of an institution, not of a house.
        $this->actingAs($user)
            ->post(route('solar-projects.appliances.store', $solarProject), [
                'space' => 'classrooms', 'key' => 'fan', 'variant' => 'stand', 'quantity' => 1, 'hours_per_day' => 8,
            ])
            ->assertSessionHasErrors(['space' => 'Ese espacio no existe en este proyecto.']);

        $this->actingAs($user)
            ->post(route('solar-projects.appliances.store', $solarProject), [
                'space' => 'living', 'key' => 'fan', 'variant' => 'stand', 'quantity' => 0, 'hours_per_day' => 30,
            ])
            ->assertSessionHasErrors(['quantity', 'hours_per_day']);

        $this->assertSame(0, $solarProject->appliances()->count());
    }

    public function test_the_page_saves_without_reloading_and_gets_the_updated_diary(): void
    {
        [$user, $solarProject] = $this->project();

        $response = $this->actingAs($user)
            ->postJson(route('solar-projects.appliances.store', $solarProject), [
                'space' => 'bedrooms', 'key' => 'air_conditioner', 'variant' => '12000.inverter', 'quantity' => 1, 'hours_per_day' => 8,
            ])
            ->assertOk()
            ->assertJson(['message' => 'Se agregó Aire acondicionado a Habitaciones.', 'space' => 'bedrooms']);

        $applianceId = $solarProject->appliances()->value('id');
        $this->assertSame($applianceId, $response->json('appliance'));
        // The re-rendered diary: new total, the row and the ring, without the page around it.
        $this->assertStringContainsString('192 kWh al mes', $response->json('html'));
        $this->assertStringContainsString('data-diary-item="'.$applianceId.'"', $response->json('html'));
        $this->assertStringNotContainsString('<html', $response->json('html'));

        $this->actingAs($user)
            ->deleteJson(route('solar-projects.appliances.destroy', [$solarProject, $applianceId]))
            ->assertOk()
            ->assertJson(['message' => 'Se quitó Aire acondicionado del proyecto.', 'space' => 'bedrooms', 'appliance' => null]);

        $this->assertSame(0, $solarProject->appliances()->count());
    }

    public function test_saving_without_reloading_reports_validation_errors_as_json(): void
    {
        [$user, $solarProject] = $this->project();

        $this->actingAs($user)
            ->postJson(route('solar-projects.appliances.store', $solarProject), [
                'space' => 'living', 'key' => 'tv', 'variant' => '99', 'quantity' => 1, 'hours_per_day' => 5,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['variant' => 'Elige una opción válida para ese equipo.']);
    }

    public function test_success_messages_are_flashes_shown_as_a_toast(): void
    {
        [$user, $solarProject] = $this->project();

        $response = $this->actingAs($user)
            ->followingRedirects()
            ->post(route('solar-projects.appliances.store', $solarProject), [
                'space' => 'living', 'key' => 'tv', 'variant' => '43', 'quantity' => 1, 'hours_per_day' => 5,
            ])
            ->assertOk();

        // Not an inline alert: the layout hands it to app.js, which shows a Flux toast for a moment.
        $this->assertMatchesRegularExpression('/<div hidden data-flash-toast data-variant="success">Se agregó Televisor a Sala y comedor\.<\/div>/', $response->getContent());
        $this->assertStringNotContainsString('solar-alert-success', $response->getContent());
    }

    public function test_a_rejected_sheet_opens_again_with_what_was_sent(): void
    {
        [$user, $solarProject] = $this->project();

        $response = $this->actingAs($user)
            ->from(route('solar-projects.consumption', $solarProject))
            ->followingRedirects()
            ->post(route('solar-projects.appliances.store', $solarProject), [
                'space' => 'living', 'key' => 'tv', 'variant' => '99', 'quantity' => 2, 'hours_per_day' => 5,
            ])
            ->assertOk()
            ->assertSee('Elige una opción válida para ese equipo.');

        $this->assertMatchesRegularExpression('/data-diary-reopen>\{"id":null,"space":"living","key":"tv","variant":"99","quantity":2/', $response->getContent());
    }

    public function test_only_the_owner_or_an_admin_can_change_the_diary(): void
    {
        [$owner, $solarProject] = $this->project();
        $appliance = $solarProject->appliances()->create(['space' => 'living', 'appliance_key' => 'tv', 'variant_key' => '43', 'quantity' => 1, 'hours_per_day' => 5]);
        $stranger = User::factory()->create();
        $payload = ['space' => 'living', 'key' => 'tv', 'variant' => '55', 'quantity' => 1, 'hours_per_day' => 5];

        $this->actingAs($stranger)->get(route('solar-projects.consumption', $solarProject))->assertForbidden();
        $this->actingAs($stranger)->post(route('solar-projects.appliances.store', $solarProject), $payload)->assertForbidden();
        $this->actingAs($stranger)->put(route('solar-projects.appliances.update', [$solarProject, $appliance]), $payload)->assertForbidden();
        $this->actingAs($stranger)->delete(route('solar-projects.appliances.destroy', [$solarProject, $appliance]))->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('solar-projects.appliances.update', [$solarProject, $appliance]), $payload)
            ->assertSessionHasNoErrors();
        $this->assertSame('55', $appliance->fresh()->variant_key);
    }

    public function test_an_appliance_of_another_project_is_not_found(): void
    {
        [$user, $solarProject] = $this->project();
        [, $otherProject] = $this->project();
        $foreign = $otherProject->appliances()->create(['space' => 'living', 'appliance_key' => 'tv', 'variant_key' => '43', 'quantity' => 1, 'hours_per_day' => 5]);

        $this->actingAs($user)
            ->delete(route('solar-projects.appliances.destroy', [$solarProject, $foreign]))
            ->assertNotFound();
        $this->assertDatabaseHas('solar_project_appliances', ['id' => $foreign->id]);
    }

    public function test_the_diary_groups_appliances_by_the_spaces_of_the_property(): void
    {
        [$user, $solarProject] = $this->project(['property_type' => 'business']);
        $solarProject->appliances()->createMany([
            ['space' => 'sales', 'appliance_key' => 'beverage_cooler', 'variant_key' => 'two_doors', 'quantity' => 1, 'hours_per_day' => 24],
            ['space' => 'office', 'appliance_key' => 'computer', 'variant_key' => 'desktop', 'quantity' => 2, 'hours_per_day' => 10],
            // A space of another kind of property ends up in "Otros".
            ['space' => 'bedrooms', 'appliance_key' => 'fan', 'variant_key' => 'stand', 'quantity' => 1, 'hours_per_day' => 8],
        ]);

        $response = $this->actingAs($user)
            ->get(route('solar-projects.consumption', $solarProject))
            ->assertOk()
            ->assertSee('Consumo de tu negocio')
            ->assertSeeInOrder(['Área de atención', 'Enfriador de bebidas', '2 puertas', 'Oficina', 'Computador', 'De escritorio · 2 × 10 horas al día', 'Bodega y cocina', 'Sin equipos todavía.', 'Otros', 'Abanico'])
            // 187.2 + 120 + 13.2 = 320.4 kWh; the cooler is the biggest (58 %).
            ->assertSee('320 kWh al mes')
            ->assertSee('Lo que más consume: <strong>Enfriador de bebidas</strong>', false)
            ->assertSee('conic-gradient(', false)
            ->assertSee('data-diary-add="storage"', false);

        $this->assertStringNotContainsString('id="espacio-kitchen"', $response->getContent());
    }

    public function test_the_sheet_has_room_for_the_chosen_appliance_in_3d(): void
    {
        [$user, $solarProject] = $this->project();

        // ADR-0019: empty until the diary script says which appliance and options; the scene fills it.
        $html = $this->actingAs($user)
            ->get(route('solar-projects.consumption', $solarProject))
            ->assertOk()
            ->assertSee('Preparando el equipo en 3D…')
            ->getContent();

        $this->assertMatchesRegularExpression('/<figure[^>]*data-appliance-scene[^>]*data-appliance=""[^>]*data-variant=""/', $html);
    }

    public function test_the_diary_can_also_show_what_each_appliance_costs_per_month(): void
    {
        // Tariff of the project: $900 per kWh.
        [$user, $solarProject] = $this->project(['property_type' => 'business']);
        $solarProject->appliances()->createMany([
            ['space' => 'sales', 'appliance_key' => 'beverage_cooler', 'variant_key' => 'two_doors', 'quantity' => 1, 'hours_per_day' => 24],
            ['space' => 'office', 'appliance_key' => 'computer', 'variant_key' => 'desktop', 'quantity' => 2, 'hours_per_day' => 10],
        ]);

        $response = $this->actingAs($user)
            ->get(route('solar-projects.consumption', $solarProject))
            ->assertOk()
            ->assertSee('data-unit-choice="money"', false)
            ->assertSee('Con tu tarifa de $900 por kWh')
            // Cooler 187.2 kWh × $900 = $168.480 → "$168.500"; computers 120 kWh → "$108.000".
            ->assertSee('<strong>$168.500</strong> al mes', false)
            ->assertSee('<strong>$108.000</strong> al mes', false)
            // Total 307.2 kWh → $276.480: "$276.500 al mes", and "$276 mil" in the ring.
            ->assertSee('<span class="solar-unit solar-unit--money">$276.500 al mes</span>', false)
            ->assertSee('<span class="solar-unit solar-unit--money">$276 mil</span>', false)
            // The kWh figures are still there: the viewer chooses which unit shows.
            ->assertSee('<span class="solar-unit solar-unit--kwh">307 kWh al mes</span>', false);

        $this->assertMatchesRegularExpression('/data-consumption-diary\s+data-unit-root\s+data-unit="kwh"\s+data-rate="900"/', $response->getContent());
    }

    public function test_without_a_tariff_the_diary_only_speaks_kwh(): void
    {
        [$user, $solarProject] = $this->project(['energy_rate_cop_kwh' => 0]);

        $this->actingAs($user)
            ->get(route('solar-projects.consumption', $solarProject))
            ->assertOk()
            ->assertDontSee('data-unit-choice="money"', false)
            ->assertDontSee('Con tu tarifa de');
    }

    public function test_an_empty_diary_invites_to_add_appliances_and_a_bill_based_project_is_explained(): void
    {
        [$user, $solarProject] = $this->project();

        $this->actingAs($user)
            ->get(route('solar-projects.consumption', $solarProject))
            ->assertOk()
            ->assertSee('Recorre tu casa y agrega tus equipos')
            ->assertSeeInOrder(['Cocina', 'Sala y comedor', 'Habitaciones', 'Lavandería y patio', 'Otros'])
            ->assertDontSee('data-test="diary-calculate"', false);

        $solarProject->update(['monthly_consumption_kwh' => 850]);

        // Older projects based on the bill keep that consumption until their first appliance.
        $this->actingAs($user)
            ->get(route('solar-projects.consumption', $solarProject))
            ->assertSee('tomado del recibo')
            ->assertSee('850 kWh al mes');
    }

    public function test_editing_the_project_data_keeps_its_appliances(): void
    {
        [$user, $solarProject] = $this->project();
        $solarProject->appliances()->create(['space' => 'kitchen', 'appliance_key' => 'fridge', 'variant_key' => 'medium.conventional', 'quantity' => 1, 'hours_per_day' => 24]);
        $municipality = Municipality::query()->create(['name' => 'Riohacha', 'department' => 'La Guajira', 'zone' => 'Base urbana', 'active' => true]);
        $municipality->solarPrices()->create(['zone_name' => 'Base urbana', 'location_type' => 'urbana', 'base_price_per_kw' => 4000000, 'logistic_factor' => 1, 'active' => true]);

        $this->actingAs($user)
            ->put(route('solar-projects.update', $solarProject), [
                'property_type' => 'house',
                'name' => 'Casa renombrada',
                'start_date' => '2026-01-01',
                'end_date' => '2026-01-31',
                'energy_rate_cop_kwh' => 900,
                'available_area_m2' => 30,
                'usable_area_percentage' => 80,
                'panel_power_w' => 550,
                'panel_area_m2' => 2.6,
                'system_losses_percentage' => 14,
                'municipality_id' => $municipality->id,
                'location_type' => 'urbana',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $solarProject->appliances()->count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: User, 1: SolarProject}
     */
    private function project(array $overrides = []): array
    {
        $user = User::factory()->create();
        $solarProject = $user->solarProjects()->create([
            'name' => 'Mi casa',
            'property_type' => 'house',
            'location_name' => SolarProject::LOCATION_NAME,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'monthly_consumption_kwh' => 0,
            'energy_rate_cop_kwh' => 900,
            ...$overrides,
        ]);
        $solarProject->technicalParameter()->create([
            'available_area_m2' => 40,
            'usable_area_percentage' => 80,
            'panel_power_w' => 550,
            'panel_area_m2' => 2.6,
            'performance_ratio' => 0.86,
            'system_losses_percentage' => 14,
        ]);

        return [$user, $solarProject];
    }
}
