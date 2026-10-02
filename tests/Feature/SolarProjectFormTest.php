<?php

namespace Tests\Feature;

use App\Domain\Solar\SystemSpecification;
use App\Models\Municipality;
use App\Models\SolarProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SolarProjectFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_asks_four_guided_questions_starting_with_the_kind_of_place(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('solar-projects.create'))
            ->assertOk()
            ->assertSee('data-project-wizard', false)
            ->assertSeeInOrder(['Tu lugar', 'Ubicación', 'Techo', 'Tu proyecto'])
            ->assertSeeInOrder([
                'data-wizard-step="property"',
                'data-wizard-step="location"',
                'data-wizard-step="roof"',
                'data-wizard-step="details"',
            ], false)
            ->assertSee('¿Para qué lugar quieres energía solar?')
            ->assertSeeInOrder(['Mi casa', 'Mi negocio', 'Mi institución'])
            // A farm is not a kind of its own: a farmhouse is a home and a productive farm a business.
            ->assertSee('en el pueblo o en el campo')
            ->assertSee('finca productiva')
            ->assertSee('data-wizard-initial="0"', false)
            ->assertSee('data-wizard-furthest="0"', false);

        foreach (['house', 'business', 'institution'] as $type) {
            $this->assertMatchesRegularExpression('/type="radio"\s+name="property_type"\s+value="'.$type.'"/', $response->getContent());
        }

        // The appliances are added later in the diary, and the notes have their own tab (ADR-0013).
        $this->assertDoesNotMatchRegularExpression('/name="(description|monthly_consumption_kwh|consumption_mode|appliances[^"]*)"/', $response->getContent());
        $this->assertDoesNotMatchRegularExpression('/data-wizard-step="(consumption|summary)"/', $response->getContent());
        // The name comes last: its field is inside the last stage.
        $this->assertMatchesRegularExpression('/data-wizard-step="details".*name="name"/s', $response->getContent());
    }

    public function test_only_a_new_project_keeps_a_per_user_draft(): void
    {
        $user = User::factory()->create();

        $create = $this->actingAs($user)
            ->get(route('solar-projects.create'))
            ->assertOk()
            ->assertSee('data-draft-key="natalia:project-draft:'.$user->id.'"', false)
            ->assertSee('Recuperamos el proyecto que estabas creando');
        $this->assertDoesNotMatchRegularExpression('/<form[^>]*data-wizard-has-errors/', $create->getContent());

        $solarProject = $user->solarProjects()->create([
            'name' => 'Casa en Maicao',
            'location_name' => SolarProject::LOCATION_NAME,
            'start_date' => '2025-03-01',
            'end_date' => '2025-03-31',
            'monthly_consumption_kwh' => 320,
            'energy_rate_cop_kwh' => 910,
        ]);

        $edit = $this->actingAs($user)
            ->get(route('solar-projects.edit', $solarProject))
            ->assertOk()
            ->assertDontSee('Recuperamos el proyecto que estabas creando');
        $this->assertDoesNotMatchRegularExpression('/<form[^>]*data-draft-key/', $edit->getContent());
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
            'name' => 'Escuela en Uribia',
            'property_type' => 'institution',
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

        $response = $this->actingAs($user)
            ->get(route('solar-projects.edit', $solarProject))
            ->assertOk()
            ->assertSee('data-wizard-furthest="2"', false)
            ->assertSee('Paso 1 de 3 · Ubicación')
            ->assertSee('value="610.00"', false)
            ->assertSee('value="2025-03-31"', false)
            ->assertSee('value="Escuela en Uribia"', false)
            ->assertDontSee('value="'.SystemSpecification::DEFAULT_PANEL_POWER_W.'"', false);

        // The kind of place is not a stage when editing: it is shown in the summary and cannot change.
        $this->assertDoesNotMatchRegularExpression('/<section[^>]*data-wizard-step="property"|<input[^>]*name="property_type"/', $response->getContent());
        $this->assertMatchesRegularExpression('/<span>Mi institución<\/span> <span class="solar-wizard-fixed">no se cambia/', $response->getContent());
    }

    public function test_editing_never_changes_the_kind_of_place(): void
    {
        $user = User::factory()->create();
        $municipality = Municipality::query()->create(['name' => 'Uribia', 'department' => 'La Guajira', 'zone' => 'Alta Guajira', 'active' => true]);
        $municipality->solarPrices()->create(['zone_name' => 'Alta Guajira', 'location_type' => 'urbana', 'base_price_per_kw' => 4700000, 'logistic_factor' => 1.18, 'active' => true]);
        $solarProject = $user->solarProjects()->create([
            'name' => 'Escuela en Uribia',
            'property_type' => 'institution',
            'location_name' => SolarProject::LOCATION_NAME,
            'start_date' => '2025-03-01',
            'end_date' => '2025-03-31',
            'monthly_consumption_kwh' => 320,
            'energy_rate_cop_kwh' => 910,
        ]);

        $this->actingAs($user)
            ->put(route('solar-projects.update', $solarProject), [
                'property_type' => 'business',
                'name' => 'Escuela en Uribia',
                'start_date' => '2025-03-01',
                'end_date' => '2025-03-31',
                'energy_rate_cop_kwh' => 890,
                'available_area_m2' => 60,
                'usable_area_percentage' => 70,
                'panel_power_w' => 580,
                'panel_area_m2' => 2.65,
                'system_losses_percentage' => 15,
                'municipality_id' => $municipality->id,
                'location_type' => 'urbana',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('solar-projects.show', $solarProject));

        $this->assertSame('institution', $solarProject->fresh()->property_type);
        $this->assertEquals(890, $solarProject->fresh()->energy_rate_cop_kwh);
    }

    public function test_validation_errors_reopen_the_first_stage_with_a_problem(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->from(route('solar-projects.create'))
            ->followingRedirects()
            ->post(route('solar-projects.store'), [
                'property_type' => 'business',
                'name' => 'Proyecto incompleto',
                'start_date' => '2026-01-01',
                'energy_rate_cop_kwh' => 800,
                'usable_area_percentage' => 80,
                'panel_power_w' => 550,
                'panel_area_m2' => 2.6,
                'system_losses_percentage' => 14,
            ]);

        // municipality_id is missing (stage 2) and available_area_m2 too (stage 3): open stage 2.
        $response->assertOk()
            ->assertSee('Revisa las etapas marcadas en rojo')
            ->assertSee('data-wizard-initial="1"', false)
            ->assertSee('data-wizard-furthest="3"', false);
        $this->assertMatchesRegularExpression('/name="property_type"\s+value="business"[^>]*checked/', $response->getContent());

        // Old input is fresher than any saved draft or stage in the URL.
        $this->assertMatchesRegularExpression('/<form[^>]*data-wizard-has-errors/', $response->getContent());
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

    public function test_the_kind_of_place_is_required_and_must_be_known(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('solar-projects.store'), ['name' => 'Sin tipo'])
            ->assertSessionHasErrors(['property_type' => 'Cuéntanos si es una casa, un negocio o una institución.']);

        $this->actingAs($user)
            ->post(route('solar-projects.store'), ['property_type' => 'castillo'])
            ->assertSessionHasErrors('property_type');
    }
}
