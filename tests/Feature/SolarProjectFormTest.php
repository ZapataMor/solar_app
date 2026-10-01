<?php

namespace Tests\Feature;

use App\Domain\Solar\SystemSpecification;
use App\Models\SolarProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SolarProjectFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_is_split_into_five_stages(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('solar-projects.create'))
            ->assertOk()
            ->assertSee('data-project-wizard', false)
            ->assertSeeInOrder(['Tu proyecto', 'Ubicación', 'Consumo', 'Espacio disponible', 'Resumen'])
            ->assertSee('data-wizard-step="project"', false)
            ->assertSee('data-wizard-step="location"', false)
            ->assertSee('data-wizard-step="consumption"', false)
            ->assertSee('data-wizard-step="space"', false)
            ->assertSee('data-wizard-step="summary"', false)
            ->assertSee('data-wizard-initial="0"', false)
            ->assertSee('data-wizard-furthest="0"', false);
    }

    public function test_create_form_prefills_technical_defaults_and_todays_date(): void
    {
        $today = now(config('app.display_timezone', config('app.timezone')))->toDateString();

        $this->actingAs(User::factory()->create())
            ->get(route('solar-projects.create'))
            ->assertOk()
            ->assertSee('name="usable_area_percentage"', false)
            ->assertSee('value="'.SystemSpecification::DEFAULT_USABLE_AREA_PERCENTAGE.'"', false)
            ->assertSee('value="'.SystemSpecification::DEFAULT_PANEL_POWER_W.'"', false)
            ->assertSee('value="'.SystemSpecification::DEFAULT_PANEL_AREA_M2.'"', false)
            ->assertSee('value="'.SystemSpecification::DEFAULT_SYSTEM_LOSSES_PERCENTAGE.'"', false)
            ->assertSee('value="'.$today.'"', false);
    }

    public function test_edit_form_uses_stored_values_and_allows_jumping_to_any_stage(): void
    {
        $user = User::factory()->create();
        $solarProject = $user->solarProjects()->create([
            'name' => 'Casa en Uribia',
            'location_name' => SolarProject::LOCATION_NAME,
            'start_date' => '2025-03-01',
            'end_date' => '2025-03-31',
            'monthly_consumption_kwh' => 320,
            'energy_rate_cop_kwh' => 910,
        ]);
        $solarProject->technicalParameter()->create([
            'available_area_m2' => 40,
            'usable_area_percentage' => 72,
            'panel_power_w' => 610,
            'panel_area_m2' => 2.7,
            'performance_ratio' => 0.85,
            'system_losses_percentage' => 15,
        ]);

        $this->actingAs($user)
            ->get(route('solar-projects.edit', $solarProject))
            ->assertOk()
            ->assertSee('data-wizard-furthest="4"', false)
            ->assertSee('value="610.00"', false)
            ->assertSee('value="2025-03-31"', false)
            ->assertDontSee('value="'.SystemSpecification::DEFAULT_PANEL_POWER_W.'"', false);
    }

    public function test_validation_errors_reopen_the_first_stage_with_a_problem(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->from(route('solar-projects.create'))
            ->followingRedirects()
            ->post(route('solar-projects.store'), [
                'name' => 'Proyecto incompleto',
                'start_date' => '2026-01-01',
                'monthly_consumption_kwh' => 300,
                'energy_rate_cop_kwh' => 800,
                'usable_area_percentage' => 80,
                'panel_power_w' => 550,
                'panel_area_m2' => 2.6,
                'system_losses_percentage' => 14,
            ]);

        // municipality_id is missing (stage 2) and available_area_m2 too (stage 4): open stage 2.
        $response->assertOk()
            ->assertSee('Revisa las etapas marcadas en rojo')
            ->assertSee('data-wizard-initial="1"', false)
            ->assertSee('data-wizard-furthest="4"', false);
    }

    public function test_errors_in_advanced_parameters_open_the_advanced_block(): void
    {
        $this->actingAs(User::factory()->create())
            ->from(route('solar-projects.create'))
            ->followingRedirects()
            ->post(route('solar-projects.store'), ['panel_power_w' => -5])
            ->assertOk()
            ->assertSee('<details class="solar-wizard-advanced mt-6"  open', false);
    }
}
