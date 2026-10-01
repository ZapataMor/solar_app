<?php

namespace Tests\Feature;

use App\Models\SolarProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ADR-0013: the description became the project's notes, and without appliances there is nothing to calculate.
 */
class ProjectNotesAndCalculationGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_notes_have_their_own_tab_and_are_kept_when_editing_the_project(): void
    {
        [$user, $solarProject] = $this->project();

        $this->actingAs($user)
            ->get(route('solar-projects.notes', $solarProject))
            ->assertOk()
            ->assertSee('Un lugar para anotar');

        $this->actingAs($user)
            ->put(route('solar-projects.notes.update', $solarProject), ['description' => 'Techo de eternit; sombra después de las 4 p. m.'])
            ->assertRedirect(route('solar-projects.notes', $solarProject))
            ->assertSessionHas('status', 'Notas guardadas.');

        $this->assertSame('Techo de eternit; sombra después de las 4 p. m.', $solarProject->fresh()->description);

        $this->actingAs(User::factory()->create())
            ->put(route('solar-projects.notes.update', $solarProject), ['description' => 'intruso'])
            ->assertForbidden();
    }

    public function test_a_project_without_appliances_cannot_be_calculated_and_says_why(): void
    {
        [$user, $solarProject] = $this->project();

        $this->actingAs($user)
            ->from(route('solar-projects.show', $solarProject))
            ->post(route('solar-projects.calculate', $solarProject))
            ->assertSessionHasErrors(['solar_calculation' => 'Agrega tus equipos en la pestaña Consumo para poder calcular tu sistema.']);

        $this->assertNull($solarProject->calculationResult()->first());
    }

    public function test_the_panel_of_a_project_without_appliances_leads_to_the_diary(): void
    {
        [$user, $solarProject] = $this->project();

        $this->actingAs($user)
            ->get(route('solar-projects.show', $solarProject))
            ->assertOk()
            ->assertSee('data-test="needs-appliances"', false)
            ->assertSee('Agrega tus equipos para ver tus resultados.')
            ->assertSee('Faltan tus equipos')
            ->assertSee(route('solar-projects.consumption', $solarProject), false);
    }

    /**
     * @return array{0: User, 1: SolarProject}
     */
    private function project(): array
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
