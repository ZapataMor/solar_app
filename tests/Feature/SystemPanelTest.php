<?php

namespace Tests\Feature;

use App\Models\AmbientWeatherReading;
use App\Models\SolarProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ADR-0014: alternative panel "Mi sistema" and the roof coverage in the consumption diary.
 */
class SystemPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_alternative_panel_is_a_tab_next_to_the_current_one(): void
    {
        [$user, $solarProject] = $this->project();

        $this->actingAs($user)
            ->get(route('solar-projects.show', $solarProject))
            ->assertOk()
            ->assertSeeInOrder(['>Técnico<', '>Mi sistema<', '>Consumo<'], false);

        $this->actingAs(User::factory()->create())
            ->get(route('solar-projects.system', $solarProject))
            ->assertForbidden();
    }

    public function test_without_appliances_it_asks_for_them_first(): void
    {
        [$user, $solarProject] = $this->project();

        $this->actingAs($user)
            ->get(route('solar-projects.system', $solarProject))
            ->assertOk()
            ->assertSee('Primero, tus equipos')
            ->assertSee('Agregar mis equipos')
            ->assertSee('data-solar-scene', false);
    }

    public function test_a_calculated_project_tells_panels_needed_and_fitting_in_money(): void
    {
        [$user, $solarProject] = $this->project();
        $solarProject->update(['monthly_consumption_kwh' => 1170]);
        $this->calculated($solarProject, needed: 15, fit: 11, installed: 11, monthlyGeneration: 891, coverage: 76.15);

        $response = $this->actingAs($user)
            ->get(route('solar-projects.system', $solarProject))
            ->assertOk()
            ->assertSee('Necesitas 15 paneles; en tu techo caben 11')
            ->assertSee('el sol pagaría 8 de cada 10 pesos de tu luz')
            ->assertSee('Cubre 76 %')
            // Still paying (1170 − 891) × 900 = $251.100.
            ->assertSee('Seguirías pagando unos $251.100 al mes')
            ->assertSee('$24,2 M')
            ->assertSee('2 años y 6 meses')
            ->assertSee('Lo que da el sol frente a lo que gastan tus equipos')
            ->assertSee('Instalamos 11 paneles: todos los que caben')
            // The 3D illustration (ADR-0012) only draws these numbers: 891 kWh a month ≈ 29,3 kWh a day.
            ->assertSee('data-panels-installed="11"', false)
            ->assertSee('data-panels-fit="11"', false)
            ->assertSee('data-panels-missing="4"', false)
            ->assertSee('data-daily-kwh="29.29"', false)
            ->assertSee('data-solar-scene-stage hidden', false)
            ->assertSee('Ilustración: no es el plano de instalación')
            ->assertDontSee('Vista 3D: próximamente');

        // Without WebGL the flat sketch stays: 11 installed panels and 4 that do not fit.
        $this->assertSame(11, substr_count($response->getContent(), 'solar-scene__panel is-installed'));
        $this->assertSame(4, substr_count($response->getContent(), 'solar-scene__panel is-missing'));
    }

    public function test_it_shows_what_the_panels_would_power_right_now(): void
    {
        [$user, $solarProject] = $this->project();
        $solarProject->update(['monthly_consumption_kwh' => 600]);
        $solarProject->appliances()->create(['space' => 'bedrooms', 'appliance_key' => 'air_conditioner', 'variant_key' => '12000.conventional', 'quantity' => 1, 'hours_per_day' => 8]);
        $this->calculated($solarProject, needed: 8, fit: 14, installed: 8, monthlyGeneration: 650, coverage: 108);
        AmbientWeatherReading::query()->create(['mac_address' => 'AA', 'recorded_at' => now()->subMinutes(10), 'solar_radiation' => 800]);

        $this->actingAs($user)
            ->get(route('solar-projects.system', $solarProject))
            ->assertOk()
            ->assertSee('Necesitas 8 paneles y te caben')
            ->assertSee('Te queda espacio para 6 paneles más')
            ->assertSee('Sol fuerte · 800 W/m²')
            // 4.4 kWp × 0.8 × 0.86 = 3.0 kW: enough for the 1.200 W air conditioner.
            ->assertSee('<strong>3,0 kW</strong>', false)
            ->assertSee('Alcanza para: Aire acondicionado.');
    }

    public function test_a_calculation_that_filled_the_roof_asks_to_be_recalculated(): void
    {
        [$user, $solarProject] = $this->project();
        $solarProject->update(['monthly_consumption_kwh' => 300]);
        $this->calculated($solarProject, needed: 4, fit: 12, installed: 12, monthlyGeneration: 980, coverage: 326);
        // Before ADR-0014 the roof was filled and the sizing was not stored.
        $solarProject->calculationResult->update(['panels_needed' => null, 'panels_that_fit' => null, 'panel_monthly_generation_kwh' => null]);

        $this->actingAs($user)
            ->get(route('solar-projects.system', $solarProject))
            ->assertOk()
            ->assertSee('Mejoramos el cálculo: ahora se instalan solo los paneles que necesitas.')
            ->assertSee('Estimado con el sol promedio de La Guajira.');
    }

    public function test_the_diary_shows_how_much_the_roof_covers_and_reacts_to_each_appliance(): void
    {
        [$user, $solarProject] = $this->project();

        // Reference sun: one 550 W panel gives 0.55 × 5.8 × 0.86 × 365 / 12 = 83.4 kWh; 12 fit (32 m² / 2.6).
        $html = $this->actingAs($user)
            ->postJson(route('solar-projects.appliances.store', $solarProject), [
                'space' => 'bedrooms', 'key' => 'air_conditioner', 'variant' => '24000.conventional', 'quantity' => 2, 'hours_per_day' => 10,
            ])
            ->assertOk()
            ->json('html');

        // 2 × 2400 W × 10 h × 30 = 1 440 kWh → 18 needed, 12 fit.
        $this->assertStringContainsString('data-test="coverage-strip"', $html);
        $this->assertStringContainsString('Necesitarías 18 paneles y caben 12: faltan 6.', $html);
        $this->assertStringContainsString('Estimado con el sol promedio de La Guajira', $html);

        $this->actingAs($user)
            ->putJson(route('solar-projects.appliances.update', [$solarProject, $solarProject->appliances()->value('id')]), [
                'space' => 'bedrooms', 'key' => 'air_conditioner', 'variant' => '12000.inverter', 'quantity' => 1, 'hours_per_day' => 8,
            ])
            ->assertOk()
            ->assertSee('Bastan 3 paneles de los 12 que caben.');
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

    private function calculated(SolarProject $solarProject, int $needed, int $fit, int $installed, float $monthlyGeneration, float $coverage): void
    {
        $capacity = $installed * 0.55;
        $solarProject->calculationResult()->create([
            'number_of_panels' => $installed,
            'panels_needed' => $needed,
            'panels_that_fit' => $fit,
            'panel_monthly_generation_kwh' => 81,
            'installed_capacity_kwp' => $capacity,
            'estimated_monthly_generation_kwh' => $monthlyGeneration,
            'annual_consumption_kwh' => $solarProject->monthlyConsumption() * 12,
            'coverage_percentage' => $coverage,
            'estimated_annual_savings_cop' => $monthlyGeneration * 900 * 12,
            'installation_cost_cop' => $capacity * 4_000_000,
            'payback_period_years' => ($capacity * 4_000_000) / ($monthlyGeneration * 900 * 12),
            'climate_source' => 'nasa_power',
        ]);
        $solarProject->monthlyResults()->create([
            'month_number' => 1,
            'month_name' => 'enero',
            'days_in_month' => 31,
            'average_daily_solar_radiation' => 5.8,
            'estimated_generation_kwh' => $monthlyGeneration * 31 / 30,
            'estimated_consumption_kwh' => $solarProject->monthlyConsumption(),
            'coverage_percentage' => $coverage,
            'estimated_savings_cop' => $monthlyGeneration * 900,
        ]);
    }
}
