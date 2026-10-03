<?php

namespace Tests\Feature;

use App\Models\AmbientWeatherReading;
use App\Models\Municipality;
use App\Models\SolarProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

/**
 * ADR-0012, last step: the 3D illustration also lives on the landing and in the project form. The scene
 * itself is drawn in the browser (not tested here): these tests only check what the server hands it.
 */
class SolarSceneShowcaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_landing_shows_a_3d_example_with_a_chooser_for_the_three_kinds_of_place(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<figure[^>]*data-solar-scene[^>]*data-scene-mode="showcase"[^>]*data-property-type="house"/s', $html);
        $this->assertStringContainsString('Preparando la ilustración 3D…', $html);
        $this->assertStringContainsString('Ilustración de ejemplo', $html);
        // The scene downloads with the page, from the head, so it arrives sooner.
        $this->assertStringContainsString(Vite::asset('resources/js/solar-scene/scene.js'), $html);

        foreach (['house' => 8, 'business' => 14, 'institution' => 20] as $type => $panels) {
            $this->assertMatchesRegularExpression('/data-scene-property="'.$type.'"\s+data-scene-panels="'.$panels.'"\s+data-scene-area="\d+"/', $html);
            // Without WebGL the flat sketch of each kind of place stays.
            $this->assertStringContainsString('data-sketch="'.$type.'"', $html);
        }
    }

    public function test_the_landing_shows_the_3d_stations_of_its_data_sources_with_the_real_wind(): void
    {
        AmbientWeatherReading::query()->create(['mac_address' => 'AA:BB', 'recorded_at' => '2026-10-02 13:00:00', 'wind_speed' => 13.4, 'wind_direction' => 45]);

        $html = $this->get(route('home'))->assertOk()->getContent();

        // One figure that follows the card chosen (ADR-0018), starting with the Ambient Weather station.
        $this->assertMatchesRegularExpression('/<figure[^>]*data-station-scene[^>]*data-station="ambient"[^>]*data-wind-speed="13.4"[^>]*data-wind-direction="45"/s', $html);
        $this->assertStringContainsString('Preparando la estación en 3D…', $html);
        $this->assertMatchesRegularExpression('/Viento de la última lectura: <span data-station-wind-text>13 km\/h del noreste<\/span>/', $html);

        foreach (['ambient', 'weather-station', 'nasa'] as $station) {
            $this->assertMatchesRegularExpression('/data-station-choice="'.$station.'"\s+aria-pressed="'.($station === 'ambient' ? 'true' : 'false').'"/', $html);
            $this->assertStringContainsString('data-station-sketch="'.$station.'"', $html);
        }
        // The cards describe each station: the figure does not repeat it.
        $this->assertStringNotContainsString('Abrigo meteorológico para temperatura', $html);
    }

    public function test_the_landing_describes_the_guided_flow_not_the_old_consumption_field(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('agrega tus equipos')
            ->assertDontSee('ingresa tu consumo');
    }

    public function test_the_create_form_previews_the_roof_with_the_panels_that_fit(): void
    {
        $html = $this->actingAs(User::factory()->create())->get(route('solar-projects.create'))->assertOk()->getContent();

        // The defaults of the form: 2.6 m² per panel and 80 % of the roof usable; without area yet, no panels.
        $this->assertMatchesRegularExpression('/<figure[^>]*data-scene-mode="preview"[^>]*data-property-type="house"[^>]*data-panels-fit="0"/s', $html);
        $this->assertStringContainsString('data-roof-preview', $html);
    }

    public function test_the_edit_form_previews_the_roof_of_the_project(): void
    {
        $user = User::factory()->create();
        $municipality = Municipality::query()->create(['name' => 'Maicao', 'department' => 'La Guajira', 'zone' => 'Media Guajira', 'active' => true]);
        $project = $user->solarProjects()->create([
            'name' => 'Local en Maicao',
            'property_type' => 'business',
            'location_name' => SolarProject::LOCATION_NAME,
            'municipality_id' => $municipality->id,
            'start_date' => '2026-07-01',
            'end_date' => '2026-09-30',
            'monthly_consumption_kwh' => 300,
        ]);
        $project->technicalParameter()->create([
            'available_area_m2' => 60,
            'usable_area_percentage' => 80,
            'panel_power_w' => 550,
            'panel_area_m2' => 2.6,
            'performance_ratio' => 0.86,
            'system_losses_percentage' => 14,
        ]);

        $html = $this->actingAs($user)->get(route('solar-projects.edit', $project))->assertOk()->getContent();

        // floor(60 × 0.8 / 2.6) = 18 panels, on the building of its kind of place.
        $this->assertMatchesRegularExpression('/<figure[^>]*data-scene-mode="preview"[^>]*data-property-type="business"[^>]*data-panels-installed="18"[^>]*data-roof-area-m2="60"/s', $html);
    }
}
