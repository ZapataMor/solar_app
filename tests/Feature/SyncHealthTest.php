<?php

namespace Tests\Feature;

use App\Actions\Climate\DescribeSyncHealth;
use App\Actions\Climate\RecordSchedulerHeartbeat;
use App\Domain\Climate\ClimateSource;
use App\Models\AmbientWeatherReading;
use App\Models\SyncRun;
use App\Models\User;
use App\Models\WeatherStationReading;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * ADR-0016: the climate data page says whether the scheduler and each source are up to date.
 */
class SyncHealthTest extends TestCase
{
    use RefreshDatabase;

    private const MAC = 'AA:BB:CC:DD:EE:FF';

    protected function setUp(): void
    {
        parent::setUp();

        // 10:00 in Bogotá: inside the local station's hours.
        $this->travelTo(Carbon::parse('2026-10-02 15:00:00', 'UTC'));
    }

    private function health(): array
    {
        return app(DescribeSyncHealth::class)();
    }

    private function ambientReading(string $recordedAtUtc): void
    {
        AmbientWeatherReading::query()->create(['mac_address' => self::MAC, 'recorded_at' => $recordedAtUtc, 'solar_radiation' => 0]);
    }

    public function test_every_sync_command_records_its_run(): void
    {
        config(['ambient.enabled' => true, 'ambient.api_key' => 'secret-api', 'ambient.application_key' => 'secret-app']);
        Sleep::fake();
        Http::fake([
            'api.ambientweather.net/v1/devices?*' => Http::response([['macAddress' => self::MAC, 'lastData' => []]]),
            'api.ambientweather.net/v1/devices/*' => Http::response([
                ['dateutc' => Carbon::now()->getTimestampMs(), 'tempf' => 80, 'solarradiation' => 100],
            ]),
        ]);

        $this->artisan('ambient:sync')->assertSuccessful();

        $run = SyncRun::query()->where('source', ClimateSource::AMBIENT)->sole();
        $this->assertSame(SyncRun::OK, $run->result);
        $this->assertSame(1, $run->created);
        $this->assertNotNull($run->finished_at);

        // The second run finds nothing new.
        $this->artisan('ambient:sync')->assertSuccessful();
        $this->assertSame(SyncRun::EMPTY, SyncRun::query()->where('source', ClimateSource::AMBIENT)->latest('id')->first()->result);
    }

    public function test_a_failed_run_keeps_its_error_without_the_api_keys(): void
    {
        config(['ambient.enabled' => true, 'ambient.api_key' => 'secret-api', 'ambient.application_key' => 'secret-app']);
        Sleep::fake();
        Http::fake(['api.ambientweather.net/*' => Http::response(['error' => 'boom'], 500)]);

        $this->artisan('ambient:sync')->assertFailed();

        $run = SyncRun::query()->sole();
        $this->assertSame(SyncRun::ERROR, $run->result);
        $this->assertNotEmpty($run->message);
        $this->assertStringNotContainsString('secret-api', $run->message);
        $this->assertStringNotContainsString('secret-app', $run->message);
    }

    public function test_a_disabled_ambient_integration_is_recorded_as_an_error(): void
    {
        config(['ambient.enabled' => false]);

        $this->artisan('ambient:sync')->assertSuccessful();

        $run = SyncRun::query()->sole();
        $this->assertSame(SyncRun::ERROR, $run->result);
        $this->assertStringContainsString('desactivada', $run->message);
    }

    public function test_the_page_warns_when_a_source_falls_behind(): void
    {
        // Ambient reported 2 h ago; the heartbeat is fresh.
        $this->ambientReading('2026-10-02 13:00:00');
        app(RecordSchedulerHeartbeat::class)();
        $admin = User::factory()->admin()->create();

        $html = $this->actingAs($admin)->get(route('api-data.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Ambient Weather lleva 2 h sin datos nuevos.', $html);
        $this->assertMatchesRegularExpression('/data-sync-source="ambient"\s+data-status="late"/', $html);
        $this->assertStringContainsString('Programador al día', $html);
    }

    public function test_the_menu_counts_the_problems_for_the_administrator_only(): void
    {
        $this->ambientReading('2026-10-02 13:00:00');
        app(RecordSchedulerHeartbeat::class)();

        $admin = User::factory()->admin()->create();
        $client = User::factory()->create();

        $this->assertSame(1, $this->health()['problems']);
        // The red badge sits in the menu entry of the climate data page, which the page itself also marks.
        $this->assertMatchesRegularExpression('/data-flux-navlist-badge[^>]*>1</', $this->actingAs($admin)->get(route('solar-projects.index'))->getContent());

        $this->ambientReading('2026-10-02 14:58:00');
        $this->assertDoesNotMatchRegularExpression('/data-flux-navlist-badge[^>]*>\d+</', $this->actingAs($admin)->get(route('solar-projects.index'))->getContent());

        // A client never sees the administration menu, nor the work of counting.
        $this->actingAs($client)->get(route('solar-projects.index'))->assertDontSee('Datos climáticos');
    }

    public function test_a_stopped_scheduler_is_a_problem(): void
    {
        app(RecordSchedulerHeartbeat::class)();
        $this->assertSame('beating', $this->health()['scheduler']['status']);

        $this->travelTo(Carbon::now()->addMinutes(11));

        $scheduler = $this->health()['scheduler'];
        $this->assertSame('stopped', $scheduler['status']);
        $this->assertTrue($scheduler['problem']);
        $this->assertStringContainsString('Lleva 11 min sin latir', $scheduler['detail']);
    }

    public function test_a_failing_source_shows_its_error(): void
    {
        $this->ambientReading('2026-10-02 13:00:00');
        SyncRun::query()->create(['source' => ClimateSource::AMBIENT, 'started_at' => now()->subMinutes(3), 'finished_at' => now()->subMinutes(3), 'result' => SyncRun::ERROR, 'message' => 'cURL error 60: SSL certificate problem']);

        $ambient = $this->health()['sources'][ClimateSource::AMBIENT];

        $this->assertSame('failing', $ambient['status']);
        $this->assertSame('cURL error 60: SSL certificate problem', $ambient['error']);
        $this->assertStringContainsString('hace 3 min · con error', $ambient['lastRun']);
    }

    public function test_the_local_station_is_not_late_at_night(): void
    {
        // 23:00 in Bogotá, with the last reading from the afternoon.
        $this->travelTo(Carbon::parse('2026-10-03 04:00:00', 'UTC'));
        WeatherStationReading::query()->create(['device_code' => 'X', 'measured_at' => '2026-10-02 22:00:00']);

        $local = $this->health()['sources'][ClimateSource::LOCAL];

        $this->assertSame('idle', $local['status']);
        $this->assertFalse($local['problem']);
    }

    public function test_the_local_station_counts_from_when_it_opens(): void
    {
        // 06:20 in Bogotá with yesterday's data: it opened 20 minutes ago, so it is not late.
        WeatherStationReading::query()->create(['device_code' => 'X', 'measured_at' => '2026-10-01 22:00:00']);

        $this->travelTo(Carbon::parse('2026-10-02 11:20:00', 'UTC'));
        $this->assertSame('ok', $this->health()['sources'][ClimateSource::LOCAL]['status']);

        $this->travelTo(Carbon::parse('2026-10-02 11:40:00', 'UTC'));
        $this->assertSame('late', $this->health()['sources'][ClimateSource::LOCAL]['status']);
    }

    public function test_the_scheduler_beats_every_minute_and_old_runs_are_pruned(): void
    {
        $events = collect(app(Schedule::class)->events());

        $this->assertTrue($events->contains(fn ($event) => $event->description === 'scheduler-heartbeat' && $event->expression === '* * * * *'));
        $this->assertTrue($events->contains(fn ($event) => str_contains((string) $event->command, 'model:prune') && str_contains((string) $event->command, 'SyncRun')));

        SyncRun::query()->create(['source' => 'ambient', 'started_at' => now()->subDays(31), 'result' => 'ok']);
        $fresh = SyncRun::query()->create(['source' => 'ambient', 'started_at' => now()->subDays(2), 'result' => 'ok']);

        $this->artisan('model:prune', ['--model' => [SyncRun::class]])->assertSuccessful();

        $this->assertSame([$fresh->id], SyncRun::query()->pluck('id')->all());
    }

    public function test_the_heartbeat_is_stored_by_its_action(): void
    {
        $this->assertNull(Cache::get(RecordSchedulerHeartbeat::CACHE_KEY));

        app(RecordSchedulerHeartbeat::class)();

        $this->assertSame(now()->getTimestamp(), Cache::get(RecordSchedulerHeartbeat::CACHE_KEY));
    }
}
