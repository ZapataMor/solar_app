<?php

namespace Tests\Feature;

use App\Actions\SolarProjects\SyncProjectConsumption;
use App\Domain\Consumption\ConsumptionMode;
use App\Models\AmbientWeatherReading;
use App\Models\Municipality;
use App\Models\SolarProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ADR-0020: the client chooses how to give the consumption, from the appliances of the diary or from the
 * kWh per month of the electricity bill.
 */
class ConsumptionModeTest extends TestCase
{
    use RefreshDatabase;

    private Municipality $municipality;

    protected function setUp(): void
    {
        parent::setUp();

        $this->municipality = Municipality::query()->create(['name' => 'Riohacha', 'department' => 'La Guajira', 'zone' => 'Base urbana', 'active' => true]);
        $this->municipality->solarPrices()->create(['zone_name' => 'Base urbana', 'location_type' => 'urbana', 'base_price_per_kw' => 4000000, 'logistic_factor' => 1, 'active' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return [
            'property_type' => 'house',
            'name' => 'Mi casa en Riohacha',
            'start_date' => '2026-07-01',
            'end_date' => '2026-09-30',
            'available_area_m2' => 60,
            'usable_area_percentage' => 75,
            'panel_power_w' => 550,
            'panel_area_m2' => 2.6,
            'system_losses_percentage' => 14,
            'municipality_id' => $this->municipality->id,
            'location_type' => 'urbana',
            ...$overrides,
        ];
    }

    private function billProject(User $user, float $kwh = 380): SolarProject
    {
        $this->actingAs($user)->post(route('solar-projects.store'), $this->form(['consumption_mode' => 'bill', 'monthly_consumption_kwh' => $kwh]))->assertSessionHasNoErrors();

        return SolarProject::query()->latest('id')->firstOrFail();
    }

    public function test_the_create_form_asks_how_to_give_the_consumption_and_chooses_nothing_for_the_client(): void
    {
        $html = $this->actingAs(User::factory()->create())->get(route('solar-projects.create'))->assertOk()->assertSee('¿Cómo calculamos tu consumo de energía?')->getContent();

        foreach (['appliances', 'bill'] as $mode) {
            $this->assertMatchesRegularExpression('/type="radio"\s+name="consumption_mode"\s+value="'.$mode.'"\s+required/', $html);
        }
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*name="consumption_mode"[^>]*\schecked/', $html);
        // The kWh of the bill wait hidden and disabled until the bill is chosen.
        $this->assertMatchesRegularExpression('/<div class="solar-consumption-bill" data-consumption-bill-field\s+hidden\s*>/', $html);
        $this->assertMatchesRegularExpression('/name="monthly_consumption_kwh"[^>]*data-consumption-kwh\s+disabled/', $html);
    }

    public function test_a_project_created_from_the_bill_keeps_those_kwh_and_goes_to_calculate(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('solar-projects.store'), $this->form(['consumption_mode' => 'bill', 'monthly_consumption_kwh' => 380]))
            ->assertSessionHasNoErrors();

        $project = SolarProject::query()->firstOrFail();
        $this->assertSame('bill', $project->consumption_mode);
        $this->assertSame(380.0, $project->monthlyConsumption());
        $this->assertEqualsWithDelta(380 * 12, (float) $project->annual_consumption_kwh, 0.01);
        // The quote already follows that consumption: power and cost wait for nothing else.
        $this->assertGreaterThan(0, (float) $project->required_power_kw);
        $this->assertGreaterThan(0, (float) $project->estimated_installation_cost);
        $this->assertTrue($project->usesBillConsumption());
    }

    public function test_the_bill_sends_the_client_to_my_system_and_the_appliances_to_the_diary(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('solar-projects.store'), $this->form(['consumption_mode' => 'bill', 'monthly_consumption_kwh' => 380]))
            ->assertRedirect(route('solar-projects.system', SolarProject::query()->firstOrFail()));

        $this->actingAs($user)->post(route('solar-projects.store'), $this->form(['name' => 'Con equipos', 'consumption_mode' => 'appliances']))
            ->assertRedirect(route('solar-projects.consumption', SolarProject::query()->where('name', 'Con equipos')->firstOrFail()));
    }

    public function test_a_project_created_from_the_appliances_starts_empty_and_ignores_a_stray_bill_number(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('solar-projects.store'), $this->form(['consumption_mode' => 'appliances', 'monthly_consumption_kwh' => 999]))
            ->assertSessionHasNoErrors();

        $project = SolarProject::query()->firstOrFail();
        $this->assertSame('appliances', $project->consumption_mode);
        $this->assertSame(0.0, $project->monthlyConsumption());
    }

    public function test_without_a_mode_the_request_keeps_the_appliances(): void
    {
        $this->actingAs(User::factory()->create())->post(route('solar-projects.store'), $this->form())->assertSessionHasNoErrors();

        $this->assertSame('appliances', SolarProject::query()->firstOrFail()->consumption_mode);
    }

    public function test_the_bill_needs_a_sensible_number_of_kwh(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('solar-projects.store'), $this->form(['consumption_mode' => 'bill']))
            ->assertSessionHasErrors(['monthly_consumption_kwh' => 'Escribe los kWh al mes que aparecen en tu recibo.']);
        $this->actingAs($user)->post(route('solar-projects.store'), $this->form(['consumption_mode' => 'bill', 'monthly_consumption_kwh' => 0]))
            ->assertSessionHasErrors('monthly_consumption_kwh');
        $this->actingAs($user)->post(route('solar-projects.store'), $this->form(['consumption_mode' => 'bill', 'monthly_consumption_kwh' => 5_000_000]))
            ->assertSessionHasErrors('monthly_consumption_kwh');
        $this->actingAs($user)->post(route('solar-projects.store'), $this->form(['consumption_mode' => 'bill', 'monthly_consumption_kwh' => 'mucho']))
            ->assertSessionHasErrors('monthly_consumption_kwh');
        $this->actingAs($user)->post(route('solar-projects.store'), $this->form(['consumption_mode' => 'nube']))
            ->assertSessionHasErrors('consumption_mode');

        $this->assertDatabaseCount('solar_projects', 0);
    }

    public function test_the_consumption_page_of_a_bill_project_shows_its_kwh_instead_of_the_diary(): void
    {
        $user = User::factory()->create();
        $project = $this->billProject($user, 380);
        // With climate data in its period, the project is ready to calculate.
        AmbientWeatherReading::query()->create(['mac_address' => 'AA:BB', 'recorded_at' => '2026-08-01 15:00:00', 'solar_radiation' => 600]);

        $this->actingAs($user)
            ->get(route('solar-projects.consumption', $project))
            ->assertOk()
            ->assertSee('380 kWh al mes')
            ->assertSee('de tu recibo')
            ->assertSee('Cambiar mi consumo')
            ->assertSee('data-test="bill-calculate"', false)
            ->assertSee('Tu techo cubre')
            ->assertDontSee('Recorre tu casa y agrega tus equipos')
            ->assertDontSee('data-diary-sheet', false);
    }

    public function test_a_bill_project_does_not_take_appliances(): void
    {
        $user = User::factory()->create();
        $project = $this->billProject($user, 380);

        $this->actingAs($user)->post(route('solar-projects.appliances.store', $project), [
            'space' => 'kitchen', 'key' => 'fridge', 'variant' => 'medium.conventional', 'quantity' => 1, 'hours_per_day' => 24,
        ])->assertStatus(409);

        $this->assertDatabaseCount('solar_project_appliances', 0);
        $this->assertSame(380.0, $project->fresh()->monthlyConsumption());
    }

    public function test_appliances_left_over_do_not_change_the_consumption_of_the_bill(): void
    {
        $user = User::factory()->create();
        $project = $this->billProject($user, 380);
        $project->appliances()->create(['space' => 'kitchen', 'appliance_key' => 'fridge', 'variant_key' => 'medium.conventional', 'quantity' => 1, 'hours_per_day' => 24]);

        app(SyncProjectConsumption::class)($project);

        $this->assertSame(380.0, $project->fresh()->monthlyConsumption());
    }

    public function test_editing_the_kwh_of_the_bill_updates_the_consumption_and_the_quote(): void
    {
        $user = User::factory()->create();
        $project = $this->billProject($user, 380);
        $power = (float) $project->required_power_kw;

        $this->actingAs($user)
            ->put(route('solar-projects.update', $project), $this->form(['consumption_mode' => 'bill', 'monthly_consumption_kwh' => 760]))
            ->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertSame(760.0, $project->monthlyConsumption());
        $this->assertEqualsWithDelta($power * 2, (float) $project->required_power_kw, 0.02);
    }

    public function test_an_update_without_a_mode_keeps_the_bill_and_its_kwh(): void
    {
        $user = User::factory()->create();
        $project = $this->billProject($user, 380);

        $this->actingAs($user)
            ->put(route('solar-projects.update', $project), $this->form(['name' => 'Renombrado']))
            ->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertSame('bill', $project->consumption_mode);
        $this->assertSame(380.0, $project->monthlyConsumption());
        $this->assertSame('Renombrado', $project->name);
    }

    public function test_changing_from_the_bill_to_the_appliances_follows_the_diary(): void
    {
        $user = User::factory()->create();
        $project = $this->billProject($user, 380);
        $project->appliances()->create(['space' => 'kitchen', 'appliance_key' => 'fridge', 'variant_key' => 'medium.conventional', 'quantity' => 1, 'hours_per_day' => 24]);

        $this->actingAs($user)
            ->put(route('solar-projects.update', $project), $this->form(['consumption_mode' => 'appliances']))
            ->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertSame('appliances', $project->consumption_mode);
        // What the fridge adds up to, not the 380 of the bill.
        $this->assertNotSame(380.0, $project->monthlyConsumption());
        $this->assertGreaterThan(0, $project->monthlyConsumption());
        $this->actingAs($user)->get(route('solar-projects.consumption', $project))->assertSee('Cocina')->assertDontSee('data-test="bill-calculate"', false)->assertDontSee('data-consumption-bill', false);
    }

    public function test_changing_from_the_appliances_to_the_bill_uses_the_number_of_the_bill(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('solar-projects.store'), $this->form())->assertSessionHasNoErrors();
        $project = SolarProject::query()->firstOrFail();
        $this->actingAs($user)->post(route('solar-projects.appliances.store', $project), [
            'space' => 'kitchen', 'key' => 'fridge', 'variant' => 'medium.conventional', 'quantity' => 1, 'hours_per_day' => 24,
        ])->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->put(route('solar-projects.update', $project), $this->form(['consumption_mode' => 'bill', 'monthly_consumption_kwh' => 500]))
            ->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertSame('bill', $project->consumption_mode);
        $this->assertSame(500.0, $project->monthlyConsumption());
        // The diary is kept: going back to it brings it back.
        $this->assertSame(1, $project->appliances()->count());
    }

    public function test_the_edit_form_offers_the_current_mode_and_starts_from_the_current_consumption(): void
    {
        $user = User::factory()->create();
        $project = $this->billProject($user, 380);

        $html = $this->actingAs($user)->get(route('solar-projects.edit', $project))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/name="consumption_mode"\s+value="bill"[^>]*checked/s', $html);
        $this->assertMatchesRegularExpression('/name="monthly_consumption_kwh"[^>]*value="380(\.00)?"/s', $html);
        $this->assertDoesNotMatchRegularExpression('/data-consumption-bill-field\s+hidden/', $html);
    }

    public function test_the_mode_labels_and_defaults(): void
    {
        $this->assertSame('appliances', ConsumptionMode::normalize(null));
        $this->assertSame('appliances', ConsumptionMode::normalize('otra cosa'));
        $this->assertSame('bill', ConsumptionMode::normalize('bill'));
        $this->assertSame('Con mi recibo de luz', ConsumptionMode::label('bill'));
        $this->assertSame('Con mis equipos', ConsumptionMode::label(null));
    }
}
