<?php

namespace Tests\Feature;

use App\Domain\Sync\SyncCadence;
use App\Jobs\SyncClimateSource;
use App\Models\SyncRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * ADR-0025: any visit to the app keeps the climate data up to date, without anybody pressing
 * anything and without the visitor waiting for it.
 */
class TrafficDrivenSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Queue::fake();
        // Off for the rest of the suite (phpunit.xml): this is the test that wants it on.
        config(['services.climate_sync.on_traffic' => true]);
    }

    public function test_a_visit_queues_the_sources_that_are_overdue(): void
    {
        $user = User::factory()->create();
        // Ambient ran a minute ago; NASA, two days ago.
        SyncRun::query()->create(['source' => SyncCadence::AMBIENT, 'started_at' => now()->subMinute()]);
        SyncRun::query()->create(['source' => SyncCadence::NASA, 'started_at' => now()->subDays(2)]);

        $this->actingAs($user)->get(route('solar-projects.index'))->assertOk();

        Queue::assertPushed(SyncClimateSource::class, fn (SyncClimateSource $job) => $job->source === SyncCadence::NASA);
        Queue::assertNotPushed(SyncClimateSource::class, fn (SyncClimateSource $job) => $job->source === SyncCadence::AMBIENT);
    }

    public function test_the_same_source_is_not_queued_twice_by_two_visits(): void
    {
        $user = User::factory()->create();
        // Only NASA is behind: the other two just ran, so they stay out of the count.
        SyncRun::query()->create(['source' => SyncCadence::AMBIENT, 'started_at' => now()]);
        SyncRun::query()->create(['source' => SyncCadence::LOCAL, 'started_at' => now()]);
        SyncRun::query()->create(['source' => SyncCadence::NASA, 'started_at' => now()->subDays(2)]);

        $this->actingAs($user)->get(route('solar-projects.index'))->assertOk();
        // Past the stamp that holds the check itself, so the second visit does look again.
        $this->travel(2)->minutes();
        $this->actingAs($user)->get(route('solar-projects.index'))->assertOk();

        // The lock per source is held for its whole interval: NASA was asked once, not twice.
        Queue::assertPushed(SyncClimateSource::class, 1);
    }

    public function test_the_climate_page_does_not_queue_behind_its_own_syncing(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        SyncRun::query()->create(['source' => SyncCadence::NASA, 'started_at' => now()->subDays(2)]);

        $this->actingAs($admin)->get(route('api-data.index'))->assertOk();

        Queue::assertNothingPushed();
    }

    public function test_a_source_never_run_is_queued_on_the_first_visit(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('solar-projects.index'))->assertOk();

        // Ambient and NASA; the local station only within its hours, which this test does not pin.
        Queue::assertPushed(SyncClimateSource::class, fn (SyncClimateSource $job) => $job->source === SyncCadence::AMBIENT);
        Queue::assertPushed(SyncClimateSource::class, fn (SyncClimateSource $job) => $job->source === SyncCadence::NASA);
    }
}
