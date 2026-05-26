<?php

namespace Tests\Feature;

use App\Models\SolarProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Responses\CreateResponse;
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
            ->assertSee('Contexto del proyecto')
            ->assertSee('Este proyecto aun no tiene parametros tecnicos registrados.')
            ->assertSee('Aun no hay datos climaticos disponibles.')
            ->assertSee('Ejecutar datos con estacion')
            ->assertSee('Ejecutar datos con NASA')
            ->assertSee('Ejecuta los calculos solares para visualizar el dashboard dinamico del proyecto.')
            ->assertDontSee('Graficas del periodo')
            ->assertDontSee('solar-timescale-chart-data', false)
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
            ->assertSee('Indicadores clave')
            ->assertSee('Capacidad instalada')
            ->assertSee('Generacion mensual')
            ->assertSee('Cobertura mensual')
            ->assertSee('Ahorro mensual')
            ->assertSee('Analisis operativo');
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
            ->assertSee('Desglose temporal')
            ->assertSee('Total del mes')
            ->assertSee('Resumen del periodo');
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
            ->assertSee('Graficas del periodo')
            ->assertSee('Generacion del mes por dia')
            ->assertSee('Consumo vs generacion del mes')
            ->assertSee('Ahorro diario acumulado del mes')
            ->assertSee('Cobertura diaria del mes')
            ->assertSee('solar-generation-chart', false)
            ->assertSee('solar-consumption-generation-chart', false)
            ->assertSee('solar-savings-chart', false)
            ->assertSee('solar-coverage-chart', false)
            ->assertSee('solar-timescale-chart-data', false)
            ->assertSee('"defaultScale":"monthly"', false)
            ->assertSee('"monthly"', false)
            ->assertSee('"annual"', false)
            ->assertSee('"daily"', false);
    }

    public function test_monthly_kpi_uses_project_monthly_consumption_not_partial_observed_consumption(): void
    {
        $user = User::factory()->create();
        $solarProject = $user->solarProjects()->create([
            ...$this->projectAttributes(),
            'monthly_consumption_kwh' => 800,
            'annual_consumption_kwh' => 9600,
        ]);
        $solarProject->technicalParameter()->create($this->technicalParameterAttributes());
        $solarProject->calculationResult()->create([
            ...$this->calculationResultAttributes(85),
            'estimated_monthly_generation_kwh' => 990.94,
            'annual_consumption_kwh' => 9600,
        ]);
        $solarProject->monthlyResults()->create([
            'month_number' => 5,
            'month_name' => 'mayo',
            'days_in_month' => 31,
            'average_daily_solar_radiation' => 5.2,
            'estimated_generation_kwh' => 990.94,
            'estimated_consumption_kwh' => 800,
            'coverage_percentage' => 123.8675,
            'estimated_savings_cop' => 812570.8,
        ]);
        $this->createPartialWeatherData($solarProject);

        $this->actingAs($user)
            ->get(route('solar-projects.show', $solarProject))
            ->assertOk()
            ->assertSee('Consumo mensual')
            ->assertSee('800,00 kWh')
            ->assertSee('Generacion mensual')
            ->assertSee('990,94 kWh')
            ->assertSee('Consumo mensual base registrado en el proyecto.');
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
            ->assertSee('Ejecuta los calculos solares para visualizar el dashboard dinamico del proyecto.')
            ->assertDontSee('Graficas del periodo')
            ->assertDontSee('solar-timescale-chart-data', false)
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
            ->assertSee('Lectura dinamica del dashboard')
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
            ->assertSee('Detalle meteorologico')
            ->assertSee('Calor extremo detectado: la temperatura actual supera los 35 C.')
            ->assertSee('Contaminacion elevada por CO2: la ventilacion del entorno deberia revisarse.')
            ->assertSee('Alta radiacion detectada: el potencial solar y la exposicion UV estan elevados.')
            ->assertSee('Historico reciente con temperatura promedio elevada: el periodo analizado ha sido caluroso.');
    }

    public function test_show_displays_energy_analysis_when_calculation_results_exist(): void
    {
        $user = User::factory()->create();
        $solarProject = $user->solarProjects()->create($this->projectAttributes());
        $solarProject->technicalParameter()->create($this->technicalParameterAttributes());
        $solarProject->calculationResult()->create([
            ...$this->calculationResultAttributes(115),
            'estimated_annual_generation_kwh' => 13800,
            'annual_consumption_kwh' => 12000,
            'estimated_annual_savings_cop' => 6200000,
        ]);
        $solarProject->monthlyResults()->create([
            'month_number' => 1,
            'month_name' => 'enero',
            'days_in_month' => 31,
            'average_daily_solar_radiation' => 5.2,
            'estimated_generation_kwh' => 1500,
            'estimated_consumption_kwh' => 1000,
            'coverage_percentage' => 150,
            'estimated_savings_cop' => 1200000,
        ]);
        $solarProject->monthlyResults()->create([
            'month_number' => 2,
            'month_name' => 'febrero',
            'days_in_month' => 28,
            'average_daily_solar_radiation' => 3.9,
            'estimated_generation_kwh' => 620,
            'estimated_consumption_kwh' => 1000,
            'coverage_percentage' => 62,
            'estimated_savings_cop' => 520000,
        ]);

        $this->actingAs($user)
            ->get(route('solar-projects.show', $solarProject))
            ->assertOk()
            ->assertSee('Analisis operativo')
            ->assertSee('Cobertura alta')
            ->assertSee('Sobreproduccion solar')
            ->assertSee('Excedentes energeticos')
            ->assertSee('Meses con excedente')
            ->assertSee('Meses con baja cobertura');
    }

    public function test_show_displays_solar_recommendations_when_weather_and_energy_data_exist(): void
    {
        $user = User::factory()->create();
        $solarProject = $user->solarProjects()->create([
            ...$this->projectAttributes(),
            'start_date' => '2026-05-23',
            'end_date' => '2026-05-23',
        ]);
        $solarProject->technicalParameter()->create($this->technicalParameterAttributes());
        $solarProject->calculationResult()->create([
            ...$this->calculationResultAttributes(58),
            'estimated_annual_generation_kwh' => 7000,
            'annual_consumption_kwh' => 12000,
            'estimated_annual_savings_cop' => 3200000,
        ]);
        $solarProject->monthlyResults()->create([
            'month_number' => 2,
            'month_name' => 'febrero',
            'days_in_month' => 28,
            'average_daily_solar_radiation' => 3.9,
            'estimated_generation_kwh' => 620,
            'estimated_consumption_kwh' => 1000,
            'coverage_percentage' => 62,
            'estimated_savings_cop' => 520000,
        ]);
        $solarProject->weatherStationReadings()->create([
            'temperature' => 35.1,
            'humidity' => 78.0,
            'thermal_sensation' => 44.0,
            'uv_index' => 6.4,
            'solar_radiation' => 740.0,
            'measured_at' => '2026-05-23 13:00:00',
        ]);

        $this->actingAs($user)
            ->get(route('solar-projects.show', $solarProject))
            ->assertOk()
            ->assertSee('Recomendaciones inteligentes')
            ->assertSee('Este mes conviene desplazar cargas flexibles a horas solares y revisar picos de consumo fuera del mediodia.')
            ->assertSee('Este mes aumento la dependencia de red y puede reducir el ahorro operativo esperado.');
    }

    public function test_show_displays_openai_recommendations_when_ai_layer_is_enabled(): void
    {
        config([
            'services.openai_recommendations.enabled' => true,
            'openai.api_key' => 'test-key',
        ]);

        $json = json_encode([
            'executive_summary' => 'Hoy se espera una produccion solar alta. Se recomienda desplazar cargas de alto consumo al mediodia.',
            'daily_recommendation' => 'Opera equipos de alto consumo entre las 11 AM y 2 PM para maximizar el ahorro energetico.',
            'energy_alerts' => [
                'La cobertura disminuye fuera de la franja solar.',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        OpenAI::fake([
            CreateResponse::fake([
                'output' => [
                    [
                        'type' => 'message',
                        'id' => 'msg_ai',
                        'status' => 'completed',
                        'role' => 'assistant',
                        'content' => [
                            [
                                'type' => 'output_text',
                                'text' => $json,
                                'annotations' => [],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $solarProject = $user->solarProjects()->create([
            ...$this->projectAttributes(),
            'start_date' => '2026-05-23',
            'end_date' => '2026-05-23',
        ]);
        $solarProject->technicalParameter()->create($this->technicalParameterAttributes());
        $solarProject->calculationResult()->create($this->calculationResultAttributes(85));
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
        $solarProject->weatherStationReadings()->create([
            'temperature' => 35.1,
            'humidity' => 78.0,
            'thermal_sensation' => 44.0,
            'uv_index' => 6.4,
            'solar_radiation' => 740.0,
            'measured_at' => '2026-05-23 13:00:00',
        ]);

        $this->actingAs($user)
            ->get(route('solar-projects.show', $solarProject))
            ->assertOk()
            ->assertSee('Hoy se espera una produccion solar alta. Se recomienda desplazar cargas de alto consumo al mediodia.')
            ->assertSee('Opera equipos de alto consumo entre las 11 AM y 2 PM para maximizar el ahorro energetico.')
            ->assertSee('La cobertura disminuye fuera de la franja solar.');
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
            ->assertDontSee('Graficas del periodo')
            ->assertDontSee('solar-timescale-chart-data', false);
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

    private function createPartialWeatherData(SolarProject $solarProject): void
    {
        foreach (range(1, 16) as $day) {
            $solarProject->weatherData()->create([
                'date_time' => sprintf('2026-05-%02d 00:00:00', $day),
                'allsky_sfc_sw_dwn' => 216.666667,
                't2m' => 28,
                'rh2m' => 75,
                'prectotcorr' => 0,
                'ws10m' => 4,
            ]);
        }
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
            'performance_ratio' => 0.86,
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
