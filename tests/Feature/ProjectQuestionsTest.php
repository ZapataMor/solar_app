<?php

namespace Tests\Feature;

use App\Models\ApiWeatherData;
use App\Models\SolarProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectQuestionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_calculated_project_shows_the_ten_questions_with_its_own_numbers(): void
    {
        [$user, $solarProject] = $this->project();
        $this->actingAs($user)->post(route('solar-projects.calculate', $solarProject));
        $result = $solarProject->calculationResult()->firstOrFail();

        $html = $this->actingAs($user)
            ->get(route('solar-projects.show', $solarProject))
            ->assertOk()
            ->assertSee('data-explainer', false)
            ->assertSee('¿Me conviene instalar paneles solares?')
            ->assertSee('¿Qué significa &quot;generación vs. consumo&quot;?', false)
            ->assertSee('¿Qué es un kWh?')
            ->assertSee('¿Qué significan estos números?')
            ->getContent();

        $this->assertSame(10, substr_count($html, 'class="solar-explainer-dot '));
        $this->assertStringContainsString("{$result->number_of_panels} paneles", $html);
        $this->assertStringContainsString('NASA POWER', $html);
    }

    public function test_an_uncalculated_project_explains_why_there_are_no_answers(): void
    {
        [$user, $solarProject] = $this->project();

        $html = $this->actingAs($user)
            ->get(route('solar-projects.show', $solarProject))
            ->assertOk()
            ->assertSee('¿Por qué no veo respuestas?')
            ->assertSee('Calcular ahora')
            ->getContent();

        $this->assertSame(1, substr_count($html, 'class="solar-explainer-dot '));
    }

    /**
     * @return array{0: User, 1: SolarProject}
     */
    private function project(): array
    {
        $user = User::factory()->create();
        $solarProject = $user->solarProjects()->create([
            'name' => 'Casa en Riohacha',
            'location_name' => SolarProject::LOCATION_NAME,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-03',
            'monthly_consumption_kwh' => 400,
            'energy_rate_cop_kwh' => 900,
        ]);
        $solarProject->technicalParameter()->create([
            'available_area_m2' => 30,
            'usable_area_percentage' => 80,
            'panel_power_w' => 550,
            'panel_area_m2' => 2.6,
            'performance_ratio' => 0.86,
            'system_losses_percentage' => 14,
        ]);

        foreach (['2026-09-01', '2026-09-02', '2026-09-03'] as $date) {
            ApiWeatherData::query()->create(['date_time' => "{$date} 00:00:00", 'allsky_sfc_sw_dwn' => 260, 'radiation_source' => 'nasa_real']);
        }

        return [$user, $solarProject];
    }
}
