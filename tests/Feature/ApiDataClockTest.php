<?php

namespace Tests\Feature;

use App\Models\AmbientWeatherReading;
use App\Models\ApiWeatherData;
use App\Models\User;
use App\Models\WeatherStationReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The hour a reading shows must be the same whether the page rendered it or a sync brought it back.
 *
 * Readings are stored in UTC and read in Bogotá (five hours behind). The rows the sync endpoints
 * answer with were formatted without that conversion, so pressing "Sincronizar" moved every row
 * five hours forward, with the same values under a different hour, until the next page load put
 * them back. Both paths share ApiDataController::displayDate() now, so pinning what the page
 * prints pins what a sync answers.
 */
class ApiDataClockTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_shows_a_reading_in_the_hour_of_bogota(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        // Noon in Bogotá, which is what the station reported.
        AmbientWeatherReading::query()->create([
            'mac_address' => 'AA:BB', 'recorded_at' => '2026-10-05 17:30:00', 'solar_radiation' => 896.48,
        ]);
        WeatherStationReading::query()->create([
            'measured_at' => '2026-10-05 17:30:00', 'solar_radiation' => 896.48,
        ]);

        $page = $this->actingAs($admin)->get(route('api-data.index'))->assertOk()->getContent();
        $this->assertStringContainsString('2026-10-05 12:30', $page);
        $this->assertStringNotContainsString('2026-10-05 17:30', $page);
    }

    public function test_the_nasa_day_is_not_moved_to_another_timezone(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        // NASA is asked by day (ADR-0009): the row is a date stamped at midnight.
        ApiWeatherData::query()->create([
            'date_time' => '2026-10-04 00:00:00', 'solar_radiation' => 250.5,
        ]);

        $page = $this->actingAs($admin)->get(route('api-data.index'))->assertOk()->getContent();
        $this->assertStringContainsString('2026-10-04', $page);
        // Converting it would read as the day before at seven in the evening.
        $this->assertStringNotContainsString('2026-10-03 19:00', $page);
    }
}
