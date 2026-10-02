<?php

namespace Tests\Feature;

use App\Models\Municipality;
use App\Models\SolarProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectDetailPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_detail_is_its_own_page_not_a_modal_over_the_portfolio(): void
    {
        [$user, $solarProject] = $this->projectWithOwner();
        $user->solarProjects()->create($this->attributes(['name' => 'Otro proyecto del portafolio']));

        $this->actingAs($user)
            ->get(route('solar-projects.show', $solarProject))
            ->assertOk()
            ->assertDontSee('solar-project-modal', false)
            ->assertDontSee('Otro proyecto del portafolio')
            ->assertSee('class="solar-project-nav"', false)
            ->assertSee('Mis proyectos')
            ->assertSee(route('solar-projects.edit', $solarProject), false);
    }

    public function test_back_link_keeps_the_portfolio_search_and_page(): void
    {
        [$user, $solarProject] = $this->projectWithOwner();

        $this->actingAs($user)
            ->get(route('solar-projects.show', ['solarProject' => $solarProject, 'search' => 'casa', 'page' => 2]))
            ->assertOk()
            ->assertSee(e(route('solar-projects.index', ['search' => 'casa', 'page' => 2])), false);
    }

    public function test_edit_page_belongs_to_the_project_and_cancel_returns_to_its_panel(): void
    {
        [$user, $solarProject] = $this->projectWithOwner();

        $this->actingAs($user)
            ->get(route('solar-projects.edit', $solarProject))
            ->assertOk()
            ->assertSee('class="solar-project-nav"', false)
            ->assertSee('Editar datos')
            ->assertSee('aria-current="page"', false)
            ->assertSee('href="'.route('solar-projects.show', $solarProject).'" class="solar-button-ghost"', false)
            ->assertSee('Guardar cambios');
    }

    public function test_saving_the_edit_goes_back_to_the_project_panel(): void
    {
        [$user, $solarProject] = $this->projectWithOwner();
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
            ->assertRedirect(route('solar-projects.show', $solarProject));

        // The consumption belongs to the diary: editing the project data keeps it.
        $this->assertSame('Casa renombrada', $solarProject->fresh()->name);
        $this->assertEquals(300, $solarProject->fresh()->monthly_consumption_kwh);
    }

    public function test_project_pages_share_the_four_tabs(): void
    {
        [$user, $solarProject] = $this->projectWithOwner();

        foreach (['solar-projects.show', 'solar-projects.consumption', 'solar-projects.notes', 'solar-projects.edit'] as $page) {
            $this->actingAs($user)
                ->get(route($page, $solarProject))
                ->assertOk()
                ->assertSeeInOrder(['>Técnico<', '>Mi sistema<', '>Consumo<', '>Notas<', '>Editar datos<'], false);
        }
    }

    /**
     * @return array{0: User, 1: SolarProject}
     */
    private function projectWithOwner(): array
    {
        $user = User::factory()->create();

        return [$user, $user->solarProjects()->create($this->attributes())];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function attributes(array $overrides = []): array
    {
        return [
            'name' => 'Casa en Riohacha',
            'location_name' => SolarProject::LOCATION_NAME,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'monthly_consumption_kwh' => 300,
            'energy_rate_cop_kwh' => 900,
            ...$overrides,
        ];
    }
}
