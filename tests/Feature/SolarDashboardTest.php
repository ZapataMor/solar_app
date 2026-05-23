<?php

namespace Tests\Feature;

use App\Models\SolarProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SolarDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_loads_when_project_has_no_calculations(): void
    {
        $user = User::factory()->create();
        $solarProject = $user->solarProjects()->create($this->projectAttributes());

        $this->actingAs($user)
            ->get(route('solar-projects.show', $solarProject))
            ->assertOk()
            ->assertSee('Informacion general del proyecto')
            ->assertSee('Este proyecto aun no tiene parametros tecnicos registrados.')
            ->assertSee('Este proyecto aun no tiene datos NASA POWER almacenados.')
            ->assertSee('Ejecutar datos con estacion')
            ->assertSee('Ejecutar datos con NASA')
            ->assertSee('Ejecuta los calculos solares para visualizar los resultados.')
            ->assertSee('Ejecuta los calculos solares para visualizar los graficos del proyecto.')
            ->assertDontSee('Graficos de resultados')
            ->assertDontSee('solar-monthly-chart-data', false)
            ->assertDontSee('solar-generation-chart', false);
    }

    public function test_show_displays_general_results_when_they_exist(): void
    {
        $user = User::factory()->create();
        $solarProject = $user->solarProjects()->create($this->projectAttributes());
        $solarProject->technicalParameter()->create($this->technicalParameterAttributes());
        $solarProject->calculationResult()->create($this->calculationResultAttributes(85));

        $this->actingAs($user)
            ->get(route('solar-projects.show', $solarProject))
            ->assertOk()
            ->assertSee('Resultados generales')
            ->assertSee('Capacidad instalada')
            ->assertSee('Generacion anual estimada')
            ->assertSee('Cobertura energetica')
            ->assertSee('Ahorro anual estimado');
    }

    public function test_show_displays_monthly_results_and_totals_when_they_exist(): void
    {
        $user = User::factory()->create();
        $solarProject = $user->solarProjects()->create($this->projectAttributes());
        $solarProject->technicalParameter()->create($this->technicalParameterAttributes());
        $solarProject->calculationResult()->create($this->calculationResultAttributes(85));
        $this->createMonthlyResults($solarProject);

        $this->actingAs($user)
            ->get(route('solar-projects.show', $solarProject))
            ->assertOk()
            ->assertSee('Resultados mensuales')
            ->assertSee('Totales anuales')
            ->assertSee('Mejores y peores meses')
            ->assertSee('Mayor generacion estimada')
            ->assertSee('Enero')
            ->assertSee('Febrero');
    }

    public function test_show_displays_chart_section_and_chart_data_when_monthly_results_exist(): void
    {
        $user = User::factory()->create();
        $solarProject = $user->solarProjects()->create($this->projectAttributes());
        $solarProject->technicalParameter()->create($this->technicalParameterAttributes());
        $solarProject->calculationResult()->create($this->calculationResultAttributes(85));
        $this->createMonthlyResults($solarProject);

        $this->actingAs($user)
            ->get(route('solar-projects.show', $solarProject))
            ->assertOk()
            ->assertSee('Graficos de resultados')
            ->assertSee('Generacion mensual estimada')
            ->assertSee('Consumo vs generacion')
            ->assertSee('Ahorro mensual estimado')
            ->assertSee('Cobertura energetica mensual')
            ->assertSee('solar-generation-chart', false)
            ->assertSee('solar-consumption-generation-chart', false)
            ->assertSee('solar-savings-chart', false)
            ->assertSee('solar-coverage-chart', false)
            ->assertSee('solar-monthly-chart-data', false)
            ->assertSee('"labels":["enero","febrero"]', false)
            ->assertSee('"generation":[1100,900]', false)
            ->assertSee('"consumption":[1000,1000]', false)
            ->assertSee('"savings":[902000,738000]', false)
            ->assertSee('"coverage":[110,90]', false);
    }

    public function test_show_does_not_render_chart_containers_when_monthly_results_do_not_exist(): void
    {
        $user = User::factory()->create();
        $solarProject = $user->solarProjects()->create($this->projectAttributes());
        $solarProject->technicalParameter()->create($this->technicalParameterAttributes());
        $solarProject->calculationResult()->create($this->calculationResultAttributes(85));

        $this->actingAs($user)
            ->get(route('solar-projects.show', $solarProject))
            ->assertOk()
            ->assertSee('Ejecuta los calculos solares para visualizar los graficos del proyecto.')
            ->assertDontSee('Graficos de resultados')
            ->assertDontSee('solar-monthly-chart-data', false)
            ->assertDontSee('solar-generation-chart', false)
            ->assertDontSee('solar-consumption-generation-chart', false)
            ->assertDontSee('solar-savings-chart', false)
            ->assertDontSee('solar-coverage-chart', false);
    }

    public function test_show_displays_coverage_interpretation_message(): void
    {
        $user = User::factory()->create();
        $solarProject = $user->solarProjects()->create($this->projectAttributes());
        $solarProject->calculationResult()->create($this->calculationResultAttributes(55));

        $this->actingAs($user)
            ->get(route('solar-projects.show', $solarProject))
            ->assertOk()
            ->assertSee('Interpretacion de resultados')
            ->assertSee('La generacion estimada tendria una cobertura media del consumo anual.');
    }

    public function test_show_displays_weather_analysis_when_station_readings_exist(): void
    {
        $user = User::factory()->create();
        $solarProject = $user->solarProjects()->create([
            ...$this->projectAttributes(),
            'start_date' => '2026-05-23',
            'end_date' => '2026-05-23',
        ]);

        $solarProject->weatherStationReadings()->create([
            'temperature' => 31.0,
            'humidity' => 54.0,
            'co2' => 700,
            'uv_index' => 2.1,
            'measured_at' => '2026-05-23 09:00:00',
        ]);
        $solarProject->weatherStationReadings()->create([
            'temperature' => 31.5,
            'humidity' => 55.0,
            'co2' => 720,
            'uv_index' => 2.2,
            'measured_at' => '2026-05-23 10:00:00',
        ]);
        $solarProject->weatherStationReadings()->create([
            'temperature' => 32.0,
            'humidity' => 56.0,
            'co2' => 740,
            'uv_index' => 2.4,
            'measured_at' => '2026-05-23 11:00:00',
        ]);
        $solarProject->weatherStationReadings()->create([
            'temperature' => 32.2,
            'humidity' => 57.0,
            'co2' => 760,
            'uv_index' => 2.6,
            'measured_at' => '2026-05-23 12:00:00',
        ]);
        $solarProject->weatherStationReadings()->create([
            'temperature' => 35.1,
            'humidity' => 78.0,
            'thermal_sensation' => 44.0,
            'co2' => 1618,
            'uv_index' => 6.4,
            'solar_radiation' => 740.0,
            'measured_at' => '2026-05-23 13:00:00',
        ]);

        $this->actingAs($user)
            ->get(route('solar-projects.show', $solarProject))
            ->assertOk()
            ->assertSee('Analisis automatico')
            ->assertSee('Estado actual')
            ->assertSee('Comportamiento historico')
            ->assertSee('Calor extremo detectado: la temperatura actual supera los 35 C.')
            ->assertSee('Contaminacion elevada por CO2: la ventilacion del entorno deberia revisarse.')
            ->assertSee('Alta radiacion detectada: el potencial solar y la exposicion UV estan elevados.')
            ->assertSee('Historico reciente con temperatura promedio elevada: el periodo analizado ha sido caluroso.');
    }

    public function test_user_cannot_view_foreign_project_summary(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $solarProject = $owner->solarProjects()->create($this->projectAttributes());
        $this->createMonthlyResults($solarProject);

        $this->actingAs($otherUser)
            ->get(route('solar-projects.show', $solarProject))
            ->assertForbidden()
            ->assertDontSee('Graficos de resultados')
            ->assertDontSee('solar-monthly-chart-data', false);
    }

    private function createMonthlyResults(SolarProject $solarProject): void
    {
        $solarProject->monthlyResults()->create([
            'month_number' => 1,
            'month_name' => 'enero',
            'days_in_month' => 31,
            'average_daily_solar_radiation' => 5.2,
            'estimated_generation_kwh' => 1100,
            'estimated_consumption_kwh' => 1000,
            'coverage_percentage' => 110,
            'estimated_savings_cop' => 902000,
        ]);

        $solarProject->monthlyResults()->create([
            'month_number' => 2,
            'month_name' => 'febrero',
            'days_in_month' => 28,
            'average_daily_solar_radiation' => 4.8,
            'estimated_generation_kwh' => 900,
            'estimated_consumption_kwh' => 1000,
            'coverage_percentage' => 90,
            'estimated_savings_cop' => 738000,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function projectAttributes(): array
    {
        return [
            'name' => 'Sistema solar institucional',
            'description' => 'Proyecto base para simulacion en Riohacha.',
            'start_date' => '2017-01-01',
            'end_date' => '2017-12-31',
            'annual_consumption_kwh' => 12000,
            'energy_rate_cop_kwh' => 820,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function technicalParameterAttributes(): array
    {
        return [
            'available_area_m2' => 120,
            'usable_area_percentage' => 85,
            'panel_power_w' => 550,
            'panel_area_m2' => 2.5,
            'performance_ratio' => 0.82,
            'system_losses_percentage' => 14,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function calculationResultAttributes(float $coveragePercentage): array
    {
        return [
            'usable_area_m2' => 102,
            'number_of_panels' => 40,
            'installed_capacity_kwp' => 22,
            'estimated_daily_generation_kwh' => 28,
            'estimated_monthly_generation_kwh' => 850,
            'estimated_annual_generation_kwh' => 12000 * ($coveragePercentage / 100),
            'annual_consumption_kwh' => 12000,
            'coverage_percentage' => $coveragePercentage,
            'estimated_annual_savings_cop' => 8364000,
        ];
    }
}
