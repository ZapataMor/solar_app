<?php

namespace Tests\Feature;

use App\Models\AmbientWeatherReading;
use App\Models\WeatherStationReading;
use App\Services\AmbientWeatherService;
use App\Services\ClimateSourceFallbackService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClimateSourceFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ambient.api_key'                  => 'test-api-key',
            'ambient.application_key'          => 'test-app-key',
            'ambient.enabled'                  => true,
            'ambient.online_threshold_minutes' => 30,
        ]);
    }

    // -----------------------------------------------------------------------
    // resolveActiveSource()
    // -----------------------------------------------------------------------

    public function test_resolves_local_when_recent_station_reading_exists(): void
    {
        WeatherStationReading::factory()->create([
            'measured_at' => Carbon::now()->subMinutes(5),
        ]);

        $source = $this->makeService()->resolveActiveSource();

        $this->assertEquals(ClimateSourceFallbackService::SOURCE_LOCAL, $source);
    }

    public function test_resolves_ambient_when_both_ambient_and_local_are_fresh(): void
    {
        WeatherStationReading::factory()->create([
            'measured_at' => Carbon::now()->subMinutes(5),
        ]);

        AmbientWeatherReading::factory()->create([
            'recorded_at' => Carbon::now()->subMinutes(5),
        ]);

        $source = $this->makeService()->resolveActiveSource();

        $this->assertEquals(ClimateSourceFallbackService::SOURCE_AMBIENT, $source);
    }

    public function test_resolves_ambient_when_local_stale_but_ambient_fresh(): void
    {
        // Local station reading is too old
        WeatherStationReading::factory()->create([
            'measured_at' => Carbon::now()->subHours(2),
        ]);

        // Ambient has a recent reading
        AmbientWeatherReading::factory()->create([
            'recorded_at' => Carbon::now()->subMinutes(10),
        ]);

        $source = $this->makeService()->resolveActiveSource();

        $this->assertEquals(ClimateSourceFallbackService::SOURCE_AMBIENT, $source);
    }

    public function test_resolves_nasa_when_both_local_and_ambient_are_stale(): void
    {
        WeatherStationReading::factory()->create([
            'measured_at' => Carbon::now()->subHours(3),
        ]);

        AmbientWeatherReading::factory()->create([
            'recorded_at' => Carbon::now()->subHours(3),
        ]);

        $source = $this->makeService()->resolveActiveSource();

        $this->assertEquals(ClimateSourceFallbackService::SOURCE_NASA, $source);
    }

    public function test_resolves_nasa_when_no_readings_exist(): void
    {
        $source = $this->makeService()->resolveActiveSource();

        $this->assertEquals(ClimateSourceFallbackService::SOURCE_NASA, $source);
    }

    public function test_resolves_nasa_when_ambient_disabled(): void
    {
        config(['ambient.enabled' => false]);

        // Ambient has a "fresh" reading, but the service is disabled
        AmbientWeatherReading::factory()->create([
            'recorded_at' => Carbon::now()->subMinutes(5),
        ]);

        $source = $this->makeService()->resolveActiveSource();

        $this->assertEquals(ClimateSourceFallbackService::SOURCE_NASA, $source);
    }

    // -----------------------------------------------------------------------
    // resolveActiveSourceDescriptor()
    // -----------------------------------------------------------------------

    public function test_descriptor_has_required_keys(): void
    {
        $descriptor = $this->makeService()->resolveActiveSourceDescriptor();

        $this->assertArrayHasKey('source', $descriptor);
        $this->assertArrayHasKey('label', $descriptor);
        $this->assertArrayHasKey('online', $descriptor);
        $this->assertArrayHasKey('fallbackUsed', $descriptor);
        $this->assertArrayHasKey('fallbackReason', $descriptor);
    }

    public function test_descriptor_fallback_used_is_false_when_ambient_is_active(): void
    {
        AmbientWeatherReading::factory()->create([
            'recorded_at' => Carbon::now()->subMinutes(5),
        ]);

        $descriptor = $this->makeService()->resolveActiveSourceDescriptor();

        $this->assertFalse($descriptor['fallbackUsed']);
        $this->assertNull($descriptor['fallbackReason']);
        $this->assertTrue($descriptor['online']);
    }

    public function test_descriptor_fallback_used_is_true_when_nasa_active(): void
    {
        $descriptor = $this->makeService()->resolveActiveSourceDescriptor();

        $this->assertTrue($descriptor['fallbackUsed']);
        $this->assertNotNull($descriptor['fallbackReason']);
        $this->assertFalse($descriptor['online']);
    }

    // -----------------------------------------------------------------------
    // selectDailyClimateRows()
    // -----------------------------------------------------------------------

    public function test_select_prefers_ambient_rows(): void
    {
        $nasa    = collect([['date_time' => now(), 'source' => 'nasa']]);
        $local   = collect([['date_time' => now(), 'source' => 'local']]);
        $ambient = collect([['date_time' => now(), 'source' => 'ambient']]);

        ['rows' => $rows, 'source' => $source] = $this->makeService()->selectDailyClimateRows(
            $nasa, $local, $ambient
        );

        $this->assertEquals('ambient', $rows->first()['source']);
        $this->assertEquals(ClimateSourceFallbackService::SOURCE_AMBIENT, $source);
    }

    public function test_select_falls_back_to_local_when_ambient_empty(): void
    {
        $nasa    = collect([['date_time' => now(), 'source' => 'nasa']]);
        $local   = collect([['date_time' => now(), 'source' => 'local']]);

        ['rows' => $rows, 'source' => $source] = $this->makeService()->selectDailyClimateRows(
            $nasa, $local, collect()
        );

        $this->assertEquals('local', $rows->first()['source']);
        $this->assertEquals(ClimateSourceFallbackService::SOURCE_LOCAL, $source);
    }

    public function test_select_falls_back_to_nasa_when_ambient_and_local_empty(): void
    {
        $nasa = collect([['date_time' => now(), 'source' => 'nasa']]);

        ['rows' => $rows, 'source' => $source] = $this->makeService()->selectDailyClimateRows(
            $nasa, collect(), collect()
        );

        $this->assertEquals('nasa', $rows->first()['source']);
        $this->assertEquals(ClimateSourceFallbackService::SOURCE_NASA, $source);
    }

    public function test_select_returns_empty_collection_when_all_sources_empty(): void
    {
        ['rows' => $rows] = $this->makeService()->selectDailyClimateRows(
            collect(), collect(), collect()
        );

        $this->assertEmpty($rows);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeService(): ClimateSourceFallbackService
    {
        return new ClimateSourceFallbackService(new AmbientWeatherService());
    }
}
