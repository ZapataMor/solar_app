<?php

namespace Tests\Feature;

use App\Actions\SolarProjects\CheckCalculationFreshness;
use App\Domain\Solar\CalculationFreshness;
use App\Models\AmbientWeatherReading;
use App\Models\ApiWeatherData;
use App\Models\SolarProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CalculationFreshnessTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_project_with_data_but_no_calculation_is_pending(): void
    {
        [$user, $solarProject] = $this->projectWithNasaData();

        $this->assertSame(CalculationFreshness::PENDING, $this->freshness($solarProject)->status);

        $this->actingAs($user)
            ->get(route('solar-projects.index'))
            ->assertSee('Sin calcular')
            ->assertSee('Recalcular proyectos')
            ->assertSee('solar-recalc-badge', false);
    }

    public function test_calculation_is_fresh_until_its_climate_data_changes_a_day_later(): void
    {
        [$user, $solarProject] = $this->projectWithNasaData();

        Carbon::setTestNow('2026-09-10 11:00:00');
        $this->actingAs($user)->post(route('solar-projects.calculate', $solarProject));
        $this->assertSame(CalculationFreshness::FRESH, $this->freshness($solarProject)->status);

        $this->actingAs($user)
            ->get(route('solar-projects.index'))
            ->assertSee('Cálculos al día')
            ->assertDontSee('Por recalcular');

        // Two days later NASA confirms a day that was an estimate.
        Carbon::setTestNow('2026-09-12 12:00:00');
        ApiWeatherData::query()->first()->update(['allsky_sfc_sw_dwn' => 260, 'radiation_source' => 'nasa_real']);

        $freshness = $this->freshness($solarProject);
        $this->assertSame(CalculationFreshness::STALE, $freshness->status);
        $this->assertSame(['Llegaron datos nuevos de NASA POWER.'], $freshness->reasons);

        $this->actingAs($user)
            ->get(route('solar-projects.show', $solarProject))
            ->assertSee('Los resultados pueden estar desactualizados.')
            ->assertSee('Llegaron datos nuevos de NASA POWER.')
            ->assertSee('Recalcular ahora');
    }

    public function test_better_source_data_marks_the_project_stale_immediately(): void
    {
        [$user, $solarProject] = $this->projectWithNasaData();

        Carbon::setTestNow('2026-09-10 11:00:00');
        $this->actingAs($user)->post(route('solar-projects.calculate', $solarProject));

        Carbon::setTestNow('2026-09-10 11:30:00');
        AmbientWeatherReading::query()->create([
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'recorded_at' => '2026-09-02 12:00:00', // inside the project period
            'solar_radiation' => 800,
            'temperature' => 31,
        ]);

        $this->assertSame(
            ['Hay datos de Ambient Weather, una fuente de mejor calidad que la usada.'],
            $this->freshness($solarProject)->reasons,
        );
    }

    public function test_recalculate_outdated_only_touches_projects_that_need_it(): void
    {
        [$user, $outdated] = $this->projectWithNasaData();
        $upToDate = $user->solarProjects()->create($this->attributes(['name' => 'Al día']));
        $upToDate->technicalParameter()->create($this->technicalParameters());

        Carbon::setTestNow('2026-09-10 11:00:00');
        $this->actingAs($user)->post(route('solar-projects.calculate', $upToDate));
        $upToDateCalculatedAt = $upToDate->calculationResult()->value('updated_at');

        $this->actingAs($user)
            ->from(route('solar-projects.index'))
            ->post(route('solar-projects.recalculate-outdated'))
            ->assertRedirect(route('solar-projects.index'))
            ->assertSessionHas('status', 'Se recalculó 1 proyecto.');

        $this->assertNotNull($outdated->calculationResult()->first());
        $this->assertSame(CalculationFreshness::FRESH, $this->freshness($outdated->fresh())->status);
        $this->assertEquals($upToDateCalculatedAt, $upToDate->calculationResult()->value('updated_at'));
    }

    public function test_recalculating_with_identical_results_clears_the_indicator(): void
    {
        [$user, $solarProject] = $this->projectWithNasaData();

        Carbon::setTestNow('2026-09-10 11:00:00');
        $this->actingAs($user)->post(route('solar-projects.calculate', $solarProject));

        Carbon::setTestNow('2026-09-12 12:00:00');
        ApiWeatherData::query()->first()->touch(); // new sync, same values
        $this->assertSame(CalculationFreshness::STALE, $this->freshness($solarProject)->status);

        $this->actingAs($user)->post(route('solar-projects.calculate', $solarProject));

        $this->assertSame(CalculationFreshness::FRESH, $this->freshness($solarProject->fresh())->status);
    }

    /**
     * @return array{0: User, 1: SolarProject}
     */
    private function projectWithNasaData(): array
    {
        Carbon::setTestNow('2026-09-10 10:00:00');
        $user = User::factory()->create();
        $solarProject = $user->solarProjects()->create($this->attributes());
        $solarProject->technicalParameter()->create($this->technicalParameters());

        foreach (['2026-09-01', '2026-09-02', '2026-09-03'] as $date) {
            ApiWeatherData::query()->create([
                'date_time' => "{$date} 00:00:00",
                'allsky_sfc_sw_dwn' => 250,
                'radiation_source' => 'nasa_real',
            ]);
        }

        return [$user, $solarProject];
    }

    private function freshness(SolarProject $solarProject): CalculationFreshness
    {
        return app(CheckCalculationFreshness::class)($solarProject->fresh());
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
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-03',
            'monthly_consumption_kwh' => 300,
            'energy_rate_cop_kwh' => 900,
            ...$overrides,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function technicalParameters(): array
    {
        return [
            'available_area_m2' => 30,
            'usable_area_percentage' => 80,
            'panel_power_w' => 550,
            'panel_area_m2' => 2.6,
            'performance_ratio' => 0.86,
            'system_losses_percentage' => 14,
        ];
    }
}
