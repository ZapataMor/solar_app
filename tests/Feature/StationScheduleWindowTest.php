<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Facade;
use Tests\TestCase;

class StationScheduleWindowTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_station_syncs_run_in_the_colombian_afternoon(): void
    {
        // 15:00 in Bogotá (20:00 UTC): inside the 06:00–18:30 window.
        $due = $this->dueStationCommandsAt('2026-10-01 20:00:00');

        $this->assertSame(['ambient:sync', 'weather-station:fetch'], $due);
    }

    public function test_station_syncs_stop_after_the_colombian_window(): void
    {
        // 20:00 in Bogotá (01:00 UTC next day): outside the window.
        $this->assertSame([], $this->dueStationCommandsAt('2026-10-02 01:00:00'));
    }

    /**
     * The window is captured when the schedule is defined, so it is rebuilt at the given time.
     *
     * @return list<string>
     */
    private function dueStationCommandsAt(string $utcNow): array
    {
        Carbon::setTestNow(Carbon::parse($utcNow, 'UTC'));

        $schedule = new Schedule('UTC');
        $this->app->instance(Schedule::class, $schedule);
        Facade::clearResolvedInstance(Schedule::class);

        require base_path('routes/console.php');

        return collect($schedule->dueEvents($this->app))
            ->filter(fn (Event $event) => $event->filtersPass($this->app))
            ->map(fn (Event $event) => preg_replace('/^.*artisan["\']?\s+/', '', $event->command))
            ->filter(fn (string $command) => in_array($command, ['weather-station:fetch', 'ambient:sync'], true))
            ->sort()
            ->values()
            ->all();
    }
}
