<?php

namespace Tests\Feature;

use App\Models\CalculationResult;
use App\Models\MonthlyResult;
use App\Models\SolarProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SolarCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculations_cannot_run_without_technical_parameters(): void
    {
        $user = User::factory()->create();
        $solarProject = $user->solarProjects()->create($this->projectAttributes());
        $this->createWeatherData($solarProject);

        $this->actingAs($user)
            ->post(route('solar-projects.calculate', $solarProject))
            ->assertSessionHasErrors('solar_calculation')
            ->assertRedirect();

        $this->assertDatabaseCount('calculation_results', 0);
        $this->assertDatabaseCount('monthly_results', 0);
    }

    public function test_calculations_cannot_run_without_weather_data(): void
    {
        $user = User::factory()->create();
        $solarProject = $user->solarProjects()->create($this->projectAttributes());
        $solarProject->technicalParameter()->create($this->technicalParameterAttributes());

        $this->actingAs($user)
            ->post(route('solar-projects.calculate', $solarProject))
            ->assertSessionHasErrors('solar_calculation')
            ->assertRedirect();

        $this->assertDatabaseCount('calculation_results', 0);
        $this->assertDatabaseCount('monthly_results', 0);
    }

    public function test_calculations_are_stored_for_project_owner(): void
    {
        $user = User::factory()->create();
        $solarProject = $user->solarProjects()->create($this->projectAttributes());
        $solarProject->technicalParameter()->create($this->technicalParameterAttributes());
        $this->createWeatherData($solarProject);

        $this->actingAs($user)
            ->post(route('solar-projects.calculate', $solarProject))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Calculos solares ejecutados correctamente.')
            ->assertRedirect();

        $calculationResult = CalculationResult::query()->whereBelongsTo($solarProject)->first();
        $monthlyResult = MonthlyResult::query()->whereBelongsTo($solarProject)->first();

        $this->assertNotNull($calculationResult);
        $this->assertNotNull($monthlyResult);
        $this->assertSame(40, $calculationResult->number_of_panels);
        $this->assertEqualsWithDelta(102, (float) $calculationResult->usable_area_m2, 0.0001);
        $this->assertEqualsWithDelta(22, (float) $calculationResult->installed_capacity_kwp, 0.0001);
        $this->assertEqualsWithDelta(15192.76, (float) $calculationResult->estimated_annual_generation_kwh, 0.1);
        $this->assertEqualsWithDelta(41.624, (float) $calculationResult->estimated_daily_generation_kwh, 0.001);
        $this->assertEqualsWithDelta(24500, (float) $calculationResult->annual_consumption_kwh, 0.1);
        $this->assertEqualsWithDelta(12458061.46, (float) $calculationResult->estimated_annual_savings_cop, 5);

        $this->assertSame(1, $monthlyResult->month_number);
        $this->assertSame(2, $monthlyResult->days_in_month);
        $this->assertEqualsWithDelta(2.2, (float) $monthlyResult->average_daily_solar_radiation, 0.0001);
        $this->assertEqualsWithDelta(83.248, (float) $monthlyResult->estimated_generation_kwh, 0.001);
        $this->assertEqualsWithDelta(24500 / 12, (float) $monthlyResult->estimated_consumption_kwh, 0.01);
    }

    public function test_running_calculations_twice_updates_general_result_and_does_not_duplicate_monthly_results(): void
    {
        $user = User::factory()->create();
        $solarProject = $user->solarProjects()->create($this->projectAttributes());
        $solarProject->technicalParameter()->create($this->technicalParameterAttributes());
        $this->createWeatherData($solarProject);

        $this->actingAs($user)->post(route('solar-projects.calculate', $solarProject));
        $firstCalculationResultId = CalculationResult::query()->whereBelongsTo($solarProject)->value('id');

        $this->actingAs($user)
            ->post(route('solar-projects.calculate', $solarProject))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame($firstCalculationResultId, CalculationResult::query()->whereBelongsTo($solarProject)->value('id'));
        $this->assertSame(1, MonthlyResult::query()->whereBelongsTo($solarProject)->count());
    }

    public function test_another_user_cannot_run_calculations_for_foreign_project(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $solarProject = $owner->solarProjects()->create($this->projectAttributes());
        $solarProject->technicalParameter()->create($this->technicalParameterAttributes());
        $this->createWeatherData($solarProject);

        $this->actingAs($otherUser)
            ->post(route('solar-projects.calculate', $solarProject))
            ->assertForbidden();

        $this->assertDatabaseCount('calculation_results', 0);
        $this->assertDatabaseCount('monthly_results', 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function projectAttributes(): array
    {
        return [
            'name' => 'Sistema solar institucional',
            'description' => 'Proyecto base para simulacion en Riohacha.',
            'location_name' => SolarProject::LOCATION_NAME,
            'latitude' => SolarProject::LATITUDE,
            'longitude' => SolarProject::LONGITUDE,
            'start_date' => '2017-01-01',
            'end_date' => '2017-01-02',
            'annual_consumption_kwh' => 24500,
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

    private function createWeatherData(SolarProject $solarProject): void
    {
        foreach ([
            ['2017-01-01 00:00:00', 83.333333],
            ['2017-01-02 00:00:00', 100],
        ] as [$dateTime, $radiation]) {
            $solarProject->weatherData()->create([
                'date_time' => $dateTime,
                'allsky_sfc_sw_dwn' => $radiation,
                't2m' => 28,
                'rh2m' => 75,
                'prectotcorr' => 0,
                'ws10m' => 4,
            ]);
        }
    }
}
