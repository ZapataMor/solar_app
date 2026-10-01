<?php

namespace Tests\Feature;

use App\Models\ApiWeatherData;
use App\Models\SolarProject;
use App\Models\User;
use App\Services\NasaWeatherDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NasaDailySyncTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_scheduled_sync_requests_daily_data(): void
    {
        Carbon::setTestNow('2026-10-01 12:00:00');
        $this->createProject('2026-09-20');
        Http::fake(['power.larc.nasa.gov/*' => Http::response($this->payload(['20260929' => 275.18], ['20260929' => 28.4]))]);

        $this->artisan('nasa-power:fetch')->assertSuccessful();

        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://power.larc.nasa.gov/api/temporal/daily/point'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/temporal/hourly/'));
        $this->assertDatabaseHas('api_weather_data', [
            'date_time' => '2026-09-29 00:00:00',
            'allsky_sfc_sw_dwn' => 275.18,
            'radiation_source' => 'nasa_real',
        ]);
    }

    public function test_an_estimate_is_confirmed_when_nasa_publishes_the_real_value(): void
    {
        $service = app(NasaWeatherDataService::class);

        // NASA has not published radiation yet: the row is stored as an estimate.
        $service->storeDailyData($this->payload(['20260929' => -999], ['20260929' => 28.4]));
        $this->assertSame('estimated', ApiWeatherData::query()->value('radiation_source'));

        // Days later NASA publishes it: the same row becomes real data.
        $result = $service->storeDailyData($this->payload(['20260929' => 275.18], ['20260929' => 28.4]));

        $this->assertSame(1, $result['promoted']);
        $this->assertDatabaseCount('api_weather_data', 1);
        $this->assertDatabaseHas('api_weather_data', [
            'date_time' => '2026-09-29 00:00:00',
            'allsky_sfc_sw_dwn' => 275.18,
            'radiation_source' => 'nasa_real',
            'radiation_fallback_method' => 'nasa_real',
        ]);
    }

    public function test_real_data_is_never_replaced_by_an_estimate(): void
    {
        $service = app(NasaWeatherDataService::class);

        $service->storeDailyData($this->payload(['20260920' => 281.5], ['20260920' => 29.0]));
        $result = $service->storeDailyData($this->payload(['20260920' => -999], ['20260920' => 29.3]));

        $this->assertSame(0, $result['promoted']);
        $this->assertDatabaseHas('api_weather_data', [
            'date_time' => '2026-09-20 00:00:00',
            'allsky_sfc_sw_dwn' => 281.5,
            'radiation_source' => 'nasa_real',
            't2m' => 29.3,
        ]);
    }

    public function test_rebuild_removes_the_old_hourly_rows(): void
    {
        Carbon::setTestNow('2026-10-01 12:00:00');
        $this->createProject('2026-09-28');
        ApiWeatherData::query()->create(['date_time' => '2026-09-28 18:00:00', 'allsky_sfc_sw_dwn' => 255.641, 'radiation_source' => 'estimated']);
        ApiWeatherData::query()->create(['date_time' => '2026-09-28 03:00:00', 'allsky_sfc_sw_dwn' => 255.641, 'radiation_source' => 'estimated']);
        Http::fake(['power.larc.nasa.gov/*' => Http::response($this->payload(['20260928' => 270.0], ['20260928' => 28.0]))]);

        $this->artisan('nasa-power:fetch', ['--rebuild' => true])->assertSuccessful();

        $this->assertSame(['2026-09-28 00:00:00'], ApiWeatherData::query()->pluck('date_time')->map(fn ($date) => (string) $date)->all());
    }

    private function createProject(string $startDate): SolarProject
    {
        return User::factory()->create()->solarProjects()->create([
            'name' => 'Proyecto NASA',
            'location_name' => SolarProject::LOCATION_NAME,
            'start_date' => $startDate,
            'end_date' => '2026-12-31',
            'monthly_consumption_kwh' => 300,
            'energy_rate_cop_kwh' => 900,
        ]);
    }

    /**
     * @param  array<string, float|int>  $allsky
     * @param  array<string, float|int>  $t2m
     * @return array<string, mixed>
     */
    private function payload(array $allsky, array $t2m): array
    {
        return ['properties' => ['parameter' => ['ALLSKY_SFC_SW_DWN' => $allsky, 'T2M' => $t2m]]];
    }
}
