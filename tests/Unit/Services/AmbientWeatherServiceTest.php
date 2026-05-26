<?php

namespace Tests\Unit\Services;

use App\Services\AmbientWeatherService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AmbientWeatherServiceTest extends TestCase
{
    private AmbientWeatherService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ambient.api_key'         => 'test-api-key',
            'ambient.application_key' => 'test-app-key',
            'ambient.enabled'         => true,
            'ambient.request_timeout' => 5,
            'ambient.cache_minutes'   => 1,
            'ambient.base_url'        => 'https://api.ambientweather.net/v1',
        ]);

        $this->service = new AmbientWeatherService();
    }

    // -----------------------------------------------------------------------
    // isEnabled()
    // -----------------------------------------------------------------------

    public function test_is_enabled_returns_false_when_api_key_missing(): void
    {
        config(['ambient.api_key' => null]);

        $this->assertFalse($this->service->isEnabled());
    }

    public function test_is_enabled_returns_false_when_disabled_in_config(): void
    {
        config(['ambient.enabled' => false]);

        $this->assertFalse($this->service->isEnabled());
    }

    public function test_is_enabled_returns_true_when_credentials_set(): void
    {
        $this->assertTrue($this->service->isEnabled());
    }

    // -----------------------------------------------------------------------
    // normalizeReading() — unit conversion
    // -----------------------------------------------------------------------

    public function test_normalize_converts_fahrenheit_to_celsius(): void
    {
        $reading = $this->reading(['tempf' => 32]);
        $this->assertEquals(0.0, $this->service->normalizeReading($reading)['temperature']);

        $reading = $this->reading(['tempf' => 212]);
        $this->assertEquals(100.0, $this->service->normalizeReading($reading)['temperature']);

        $reading = $this->reading(['tempf' => 98.6]);
        $this->assertEquals(37.0, $this->service->normalizeReading($reading)['temperature']);
    }

    public function test_normalize_converts_mph_to_kmh(): void
    {
        $reading = $this->reading(['windspeedmph' => 1]);
        $result = $this->service->normalizeReading($reading);
        $this->assertEqualsWithDelta(1.60934, $result['wind_speed'], 0.001);
    }

    public function test_normalize_converts_inches_to_mm(): void
    {
        $reading = $this->reading(['hourlyrainin' => 1]);
        $result = $this->service->normalizeReading($reading);
        $this->assertEqualsWithDelta(25.4, $result['rainfall'], 0.001);
    }

    public function test_normalize_passes_solar_radiation_as_is(): void
    {
        $reading = $this->reading(['solarradiation' => 512.5]);
        $result = $this->service->normalizeReading($reading);
        $this->assertEquals(512.5, $result['solar_radiation']);
    }

    public function test_normalize_accepts_epoch_ms_timestamp(): void
    {
        $epochMs = 1716854400000; // 2024-05-28 00:00:00 UTC
        $reading = $this->reading(['dateutc' => $epochMs]);
        $result = $this->service->normalizeReading($reading);
        $this->assertStringContainsString('2024-05-28', $result['recorded_at']);
    }

    public function test_normalize_accepts_iso_timestamp(): void
    {
        $reading = $this->reading(['dateutc' => '2024-05-28T12:00:00.000Z']);
        $result = $this->service->normalizeReading($reading);
        $this->assertStringContainsString('2024-05-28', $result['recorded_at']);
    }

    public function test_normalize_returns_null_for_missing_fields(): void
    {
        $result = $this->service->normalizeReading([]);

        $this->assertNull($result['recorded_at']);
        $this->assertNull($result['temperature']);
        $this->assertNull($result['humidity']);
        $this->assertNull($result['wind_speed']);
        $this->assertNull($result['rainfall']);
        $this->assertNull($result['uv_index']);
        $this->assertNull($result['solar_radiation']);
    }

    public function test_normalize_treats_ambient_sentinel_as_null(): void
    {
        $reading = $this->reading([
            'tempf'         => -9999,
            'solarradiation'=> -9999,
        ]);

        $result = $this->service->normalizeReading($reading);
        $this->assertNull($result['temperature']);
        $this->assertNull($result['solar_radiation']);
    }

    // -----------------------------------------------------------------------
    // getDevices() — HTTP fake
    // -----------------------------------------------------------------------

    public function test_get_devices_returns_empty_when_disabled(): void
    {
        config(['ambient.enabled' => false]);

        $devices = (new AmbientWeatherService())->getDevices();

        $this->assertEmpty($devices);
    }

    public function test_get_devices_parses_api_response(): void
    {
        Cache::flush();

        Http::fake([
            'api.ambientweather.net/*' => Http::response([
                ['macAddress' => 'AA:BB:CC:DD:EE:FF', 'info' => ['name' => 'Rooftop']],
            ], 200),
        ]);

        $devices = $this->service->getDevices();

        $this->assertCount(1, $devices);
        $this->assertEquals('AA:BB:CC:DD:EE:FF', $devices->first()['macAddress']);
    }

    public function test_get_devices_returns_empty_on_rate_limit(): void
    {
        Cache::flush();

        Http::fake([
            'api.ambientweather.net/*' => Http::response([], 429),
        ]);

        $devices = $this->service->getDevices();

        $this->assertEmpty($devices);
    }

    // -----------------------------------------------------------------------
    // getLatestData() — HTTP fake
    // -----------------------------------------------------------------------

    public function test_get_latest_data_normalises_returned_reading(): void
    {
        Cache::flush();

        $raw = $this->rawAmbientReading();

        Http::fake([
            'api.ambientweather.net/*' => Http::response([$raw], 200),
        ]);

        $result = $this->service->getLatestData('AA:BB:CC:DD:EE:FF');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('temperature', $result);
        $this->assertArrayHasKey('recorded_at', $result);
        // 77°F should convert to 25°C
        $this->assertEqualsWithDelta(25.0, $result['temperature'], 0.1);
    }

    public function test_get_latest_data_returns_null_when_disabled(): void
    {
        config(['ambient.enabled' => false]);

        $result = (new AmbientWeatherService())->getLatestData('AA:BB:CC:DD:EE:FF');

        $this->assertNull($result);
    }

    // -----------------------------------------------------------------------
    // buildQueryParams()
    // -----------------------------------------------------------------------

    public function test_build_query_params_includes_credentials(): void
    {
        $params = $this->service->buildQueryParams();

        $this->assertArrayHasKey('apiKey', $params);
        $this->assertArrayHasKey('applicationKey', $params);
        $this->assertEquals('test-api-key', $params['apiKey']);
        $this->assertEquals('test-app-key', $params['applicationKey']);
    }

    public function test_build_query_params_merges_extra_fields(): void
    {
        $params = $this->service->buildQueryParams(['limit' => 10]);

        $this->assertEquals(10, $params['limit']);
        $this->assertArrayHasKey('apiKey', $params);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function reading(array $overrides = []): array
    {
        return array_merge($this->rawAmbientReading(), $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function rawAmbientReading(): array
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
