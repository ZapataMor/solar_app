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
            ->assertSee('Este proyecto aun no tiene datos climaticos sincronizados.')
            ->assertSee('Ejecuta los calculos solares para visualizar los resultados.');
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

    public function test_user_cannot_view_dashboard_for_foreign_project(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $solarProject = $owner->solarProjects()->create($this->projectAttributes());

        $this->actingAs($otherUser)
            ->get(route('solar-projects.show', $solarProject))
            ->assertForbidden();
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
