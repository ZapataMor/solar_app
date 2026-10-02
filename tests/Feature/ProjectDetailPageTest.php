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

    public function test_portfolio_searches_while_typing_and_only_renders_the_results_it_replaces(): void
    {
        [$user] = $this->projectWithOwner();
        $user->solarProjects()->create($this->attributes(['name' => 'Local del centro']));

        $response = $this->actingAs($user)
            ->get(route('solar-projects.index', ['search' => 'local']))
            ->assertOk()
            ->assertSee('Local del centro')
            ->assertDontSee('Casa en Riohacha')
            ->assertDontSee('>Buscar</button>', false);

        // app.js replaces [data-portfolio-results] with the same block of the searched page.
        $this->assertMatchesRegularExpression('/<form[^>]*data-portfolio-search/', $response->getContent());
        $this->assertMatchesRegularExpression('/<div[^>]*data-portfolio-results[^>]*>.*Local del centro/s', $response->getContent());
        $this->assertMatchesRegularExpression('/<a[^>]*data-portfolio-clear(?![^>]*hidden)/', $response->getContent());
    }

    public function test_cards_show_cost_payback_and_a_profitability_ribbon_and_the_table_compares_them(): void
    {
        [$user, $fast] = $this->projectWithOwner();
        $fast->update(['name' => 'Casa que se paga rápido']);
        $fast->calculationResult()->create(['installation_cost_cop' => 14_440_000, 'payback_period_years' => 2.5, 'climate_source' => 'nasa_power']);
        $slow = $user->solarProjects()->create($this->attributes(['name' => 'Local que tarda']));
        $slow->calculationResult()->create(['installation_cost_cop' => 30_000_000, 'payback_period_years' => 12.2, 'climate_source' => 'nasa_power']);
        $pending = $user->solarProjects()->create($this->attributes(['name' => 'Escuela sin calcular', 'estimated_installation_cost' => 20_000_000]));

        $html = $this->actingAs($user)
            ->get(route('solar-projects.index'))
            ->assertOk()
            ->assertSeeInOrder(['Escuela sin calcular', '$20,0 M', 'Calcúlalo para saberlo'])
            ->assertSeeInOrder(['Local que tarda', '$30,0 M', '12 años y 2 meses'])
            ->assertSeeInOrder(['Casa que se paga rápido', '$14,4 M', '2 años y 6 meses'])
            ->assertSee('title="Ver como tabla"', false)
            ->getContent();

        // Ribbons only on calculated projects.
        $this->assertSame(1, preg_match_all('/solar-project-ribbon--good[^>]*>\s*Rentable/', $html));
        $this->assertSame(1, preg_match_all('/solar-project-ribbon--poor[^>]*>\s*Poco rentable/', $html));
        $this->assertSame(2, substr_count($html, 'data-test="ribbon"'));

        // The table: same projects, inside the results so the live search refreshes it.
        $this->assertMatchesRegularExpression('/data-portfolio-results.*<dialog[^>]*data-portfolio-table/s', $html);
        $this->assertMatchesRegularExpression('/<dialog.*Casa que se paga rápido.*\$14\.440\.000.*2 años y 6 meses.*Rentable.*<\/dialog>/s', $html);
        $this->assertMatchesRegularExpression('/<dialog.*Escuela sin calcular.*\$20\.000\.000.*Sin calcular.*<\/dialog>/s', $html);
    }

    public function test_a_project_opens_in_mi_sistema_and_its_back_link_keeps_the_search(): void
    {
        [$user, $solarProject] = $this->projectWithOwner();
        $system = route('solar-projects.system', ['solarProject' => $solarProject, 'search' => 'casa']);

        $this->actingAs($user)
            ->get(route('solar-projects.index', ['search' => 'casa']))
            ->assertOk()
            ->assertSee('href="'.e($system).'"', false);

        $this->actingAs($user)
            ->get($system)
            ->assertOk()
            ->assertSee('href="'.e(route('solar-projects.index', ['search' => 'casa'])).'" class="solar-project-nav__back"', false);
    }

    public function test_the_card_and_the_project_pages_share_a_transition_name(): void
    {
        [$user, $solarProject] = $this->projectWithOwner();
        $name = 'view-transition-name: project-'.$solarProject->id;

        // A full navigation (no wire:navigate) so the browser can morph the card into the page.
        $card = $this->actingAs($user)->get(route('solar-projects.index'))->getContent();
        $this->assertMatchesRegularExpression('/<a(?![^>]*wire:navigate)[^>]*class="solar-project-card"[^>]*'.preg_quote($name, '/').'"/', $card);

        foreach (['solar-projects.show', 'solar-projects.system', 'solar-projects.consumption', 'solar-projects.notes', 'solar-projects.edit'] as $page) {
            $this->actingAs($user)
                ->get(route($page, $solarProject))
                ->assertOk()
                ->assertSee('class="solar-project-detail" style="'.$name.'"', false)
                ->assertSee('view-transition-name: project-title-'.$solarProject->id, false);
        }
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
