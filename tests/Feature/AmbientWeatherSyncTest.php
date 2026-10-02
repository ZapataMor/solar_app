<?php

namespace Tests\Feature;

use App\Models\AmbientWeatherReading;
use App\Models\User;
use App\Services\AmbientWeatherImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * Ambient Weather sync brings each station up to now from its last stored reading, at the API's pace.
 * The API is faked: no network.
 */
class AmbientWeatherSyncTest extends TestCase
{
    use RefreshDatabase;

    private const MAC = 'AA:BB:CC:DD:EE:FF';

    protected function setUp(): void
    {
        parent::setUp();

        config(['ambient.enabled' => true, 'ambient.api_key' => 'secret-api', 'ambient.application_key' => 'secret-app']);
        Sleep::fake();
        $this->travelTo(Carbon::parse('2026-10-02 05:20:00', 'UTC'));
    }

    public function test_it_fills_the_gap_since_the_last_stored_reading_up_to_now(): void
    {
        // Last stored: 7 p. m. in Colombia (00:00 UTC). The API page goes from 00:20 Colombia (05:20 UTC)
        // back past it, as the real one does (up to 288 readings).
        AmbientWeatherReading::query()->create(['mac_address' => self::MAC, 'recorded_at' => '2026-10-02 00:00:00', 'solar_radiation' => 0]);
        $this->fakeApi(device: Http::response($this->readings('2026-10-02 05:20', 100)));

        $summary = app(AmbientWeatherImportService::class)->importRecentForAllDevices();

        $this->assertSame(64, $summary['created']);
        $this->assertSame('2026-10-02 05:20:00', $summary['latest']->toDateTimeString());
        $this->assertSame(65, AmbientWeatherReading::query()->count());
        // The list of stations and one page of readings, spaced at the API's pace.
        Http::assertSentCount(2);
    }

    public function test_a_429_waits_and_tries_again_instead_of_failing(): void
    {
        $this->fakeApi(device: Http::sequence()
            ->push(['error' => 'above-user-rate-limit'], 429)
            ->push($this->readings('2026-10-02 05:20', 3))
            ->whenEmpty(Http::response([])));

        $summary = app(AmbientWeatherImportService::class)->importRecentForAllDevices();

        $this->assertSame(3, $summary['created']);
        Sleep::assertSlept(fn ($duration) => $duration->totalMilliseconds >= 1500);
    }

    public function test_errors_never_carry_the_api_keys(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 60: SSL certificate problem for https://api.ambientweather.net/v1/devices?apiKey=secret-api&applicationKey=secret-app'));

        try {
            app(AmbientWeatherImportService::class)->importRecentForAllDevices();
            $this->fail('The connection error should reach the caller.');
        } catch (ConnectionException $exception) {
            $this->assertStringNotContainsString('secret-', $exception->getMessage());
            $this->assertStringContainsString('apiKey=***&applicationKey=***', $exception->getMessage());
        }
    }

    public function test_the_button_says_how_many_readings_arrived_and_the_latest_time(): void
    {
        AmbientWeatherReading::query()->create(['mac_address' => self::MAC, 'recorded_at' => '2026-10-02 05:10:00', 'solar_radiation' => 0]);
        $this->fakeApi(device: Http::response($this->readings('2026-10-02 05:20', 3)));

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->postJson(route('api-data.fetch-ambient-data'))
            ->assertOk()
            // 05:20 UTC is 12:20 a. m. in Colombia.
            ->assertJsonPath('message', 'Ambient Weather al día: 2 lecturas nuevas. La última es de las 12:20 a. m. del 2 de octubre.');
    }

    private function fakeApi($device): void
    {
        Http::fake([
            'api.ambientweather.net/v1/devices?*' => Http::response([['macAddress' => self::MAC, 'lastData' => []]]),
            'api.ambientweather.net/v1/devices/*' => $device,
        ]);
    }

    /**
     * Readings every 5 minutes, newest first (as the API sends them).
     *
     * @return list<array<string, mixed>>
     */
    private function readings(string $newestUtc, int $count): array
    {
        $newest = Carbon::parse($newestUtc, 'UTC');

        return array_map(fn (int $index) => [
            'dateutc' => $newest->copy()->subMinutes(5 * $index)->getTimestampMs(),
            'tempf' => 80,
            'solarradiation' => 0,
        ], range(0, $count - 1));
    }
}
