<?php

namespace Tests\Feature;

use App\Models\AmbientWeatherReading;
use App\Models\User;
use App\Services\AmbientWeatherImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * ADR-0018: the hero of the climate data page shows the station behind the selected tab, instead of
 * repeating the counts that the tabs and each section already show.
 */
class DataStationSceneTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_hero_shows_the_station_of_the_selected_tab_and_the_counts_stay_in_the_tabs(): void
    {
        $admin = User::factory()->admin()->create();
        $this->reading(['wind_speed' => 13.4, 'wind_direction' => 45, 'recorded_at' => '2026-05-21 13:40:00']);
        // The newest reading has no wind: the vane keeps the last one that had it.
        $this->reading(['wind_speed' => null, 'wind_direction' => null, 'recorded_at' => '2026-05-21 13:45:00']);

        $html = $this->actingAs($admin)
            ->get(route('api-data.index', ['tab' => 'nasa']))
            ->assertOk()
            ->assertSee('Viento de la última lectura: <span data-station-wind-text>13 km/h del noreste</span>', false)
            ->assertSee('Satélites de NASA POWER')
            ->assertDontSee('Total registros')
            ->assertDontSee('Base consolidada para decisiones')
            ->getContent();

        $this->assertMatchesRegularExpression('/<figure[^>]*data-station-scene[^>]*data-station="nasa"[^>]*data-wind-speed="13.4"[^>]*data-wind-direction="45"/', $html);
        $this->assertMatchesRegularExpression('/<figure[^>]*data-latitude="11.5444"[^>]*data-longitude="-72.9072"/', $html);
        // The sync updates these numbers, and the total adds them up.
        $this->assertMatchesRegularExpression('/<span data-ambient-count data-count="2">2<\/span> registros/', $html);
        $this->assertMatchesRegularExpression('/<span data-api-data-nasa-count data-count="0">0<\/span> registros/', $html);
    }

    public function test_without_readings_the_vane_has_no_wind_to_show(): void
    {
        $admin = User::factory()->admin()->create();

        $html = $this->actingAs($admin)
            ->get(route('api-data.index'))
            ->assertOk()
            ->assertSee('sin lecturas todavía')
            ->getContent();

        $this->assertMatchesRegularExpression('/<figure[^>]*data-station="ambient"[^>]*data-wind-speed=""[^>]*data-wind-direction=""/', $html);
        $this->assertMatchesRegularExpression('/<svg[^>]*data-no-direction[^>]*data-station-compass/', $html);
    }

    public function test_the_ambient_sync_sends_the_new_wind_so_the_vane_follows_it(): void
    {
        $admin = User::factory()->admin()->create();
        $this->app->instance(AmbientWeatherImportService::class, new class extends AmbientWeatherImportService
        {
            public function __construct() {}

            public function importRecentForAllDevices(int $maxDays = 7): array
            {
                AmbientWeatherReading::query()->create([
                    'mac_address' => 'AA:BB:CC:DD:EE:FF',
                    'wind_speed' => 0.3,
                    'wind_direction' => 167,
                    'recorded_at' => '2026-05-21 13:45:00',
                ]);

                return ['received' => 1, 'created' => 1, 'skipped' => 0, 'latest' => Carbon::parse('2026-05-21 13:45:00', 'UTC')];
            }
        });

        $this->actingAs($admin)
            ->postJson(route('api-data.fetch-ambient-data'), ['auto_sync' => true])
            ->assertOk()
            ->assertJsonPath('wind.speedKmh', 0.3)
            ->assertJsonPath('wind.directionDegrees', 167)
            ->assertJsonPath('wind.text', 'en calma');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function reading(array $attributes): AmbientWeatherReading
    {
        return AmbientWeatherReading::query()->create([
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'temperature' => 30.2,
            ...$attributes,
        ]);
    }
}
