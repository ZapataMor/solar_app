<?php

namespace Tests\Unit\Services;

use App\Models\AmbientWeatherReading;
use App\Services\AmbientWeatherImportService;
use App\Services\AmbientWeatherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AmbientWeatherImportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ambient.api_key'         => 'test-api-key',
            'ambient.application_key' => 'test-app-key',
            'ambient.enabled'         => true,
            'ambient.request_timeout' => 5,
            'ambient.cache_minutes'   => 0,
            'ambient.base_url'        => 'https://api.ambientweather.net/v1',
        ]);
    }

    public function test_import_returns_zeroes_when_ambient_disabled(): void
    {
        config(['ambient.enabled' => false]);

        $service = $this->makeService();
        $result = $service->importLatestForAllDevices();

        $this->assertEquals(['received' => 0, 'created' => 0, 'skipped' => 0], $result);
    }

    public function test_import_creates_reading_for_each_device(): void
    {
        Cache::flush();

        $mac = 'AA:BB:CC:DD:EE:FF';

        Http::fake([
            // getDevices()
            'api.ambientweather.net/v1/devices' => Http::response([
                ['macAddress' => $mac],
            ], 200),
            // getLatestData()
            "api.ambientweather.net/v1/devices/{$mac}" => Http::response([
                $this->rawReading(),
            ], 200),
        ]);

        $result = $this->makeService()->importLatestForAllDevices();

        $this->assertEquals(1, $result['received']);
        $this->assertEquals(1, $result['created']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertDatabaseCount('ambient_weather_readings', 1);
    }

    public function test_import_skips_duplicate_mac_plus_timestamp(): void
    {
        Cache::flush();

        $mac = 'AA:BB:CC:DD:EE:FF';

        Http::fake([
            'api.ambientweather.net/v1/devices' => Http::response([
                ['macAddress' => $mac],
            ], 200),
            "api.ambientweather.net/v1/devices/{$mac}" => Http::response([
                $this->rawReading(),
            ], 200),
        ]);

        $service = $this->makeService();
        $service->importLatestForAllDevices();

        Cache::flush();

        // Re-queue same fake response for second call
        Http::fake([
            'api.ambientweather.net/v1/devices' => Http::response([
                ['macAddress' => $mac],
            ], 200),
            "api.ambientweather.net/v1/devices/{$mac}" => Http::response([
                $this->rawReading(),
            ], 200),
        ]);

        $result = $service->importLatestForAllDevices();

        $this->assertEquals(1, $result['skipped']);
        $this->assertDatabaseCount('ambient_weather_readings', 1);
    }

    public function test_import_stores_normalised_values(): void
    {
        Cache::flush();

        $mac = 'AA:BB:CC:DD:EE:FF';

        Http::fake([
            'api.ambientweather.net/v1/devices' => Http::response([['macAddress' => $mac]], 200),
            "api.ambientweather.net/v1/devices/{$mac}" => Http::response([$this->rawReading()], 200),
        ]);

        $this->makeService()->importLatestForAllDevices();

        $record = AmbientWeatherReading::first();
        $this->assertNotNull($record);
        $this->assertEquals($mac, $record->mac_address);
        // 77°F → 25°C
        $this->assertEqualsWithDelta(25.0, (float) $record->temperature, 0.1);
        // 5 mph → ~8.047 km/h
        $this->assertEqualsWithDelta(8.047, (float) $record->wind_speed, 0.1);
    }

    public function test_import_handles_missing_mac_address_gracefully(): void
    {
        Cache::flush();

        Http::fake([
            'api.ambientweather.net/v1/devices' => Http::response([
                ['info' => ['name' => 'no-mac-device']],
            ], 200),
        ]);

        $result = $this->makeService()->importLatestForAllDevices();

        $this->assertEquals(0, $result['received']);
        $this->assertDatabaseCount('ambient_weather_readings', 0);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeService(): AmbientWeatherImportService
    {
        return new AmbientWeatherImportService(new AmbientWeatherService());
    }

    /**
     * @return array<string, mixed>
     */
    private function rawReading(): array
    {
        return [
            'dateutc'        => 1716854400000,
            'tempf'          => 77.0,
            'humidity'       => 65,
            'windspeedmph'   => 5.0,
            'winddir'        => 180,
            'hourlyrainin'   => 0.0,
            'uv'             => 3,
            'solarradiation' => 420.5,
        ];
    }
}
