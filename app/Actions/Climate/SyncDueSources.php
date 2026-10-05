<?php

namespace App\Actions\Climate;

use App\Domain\Sync\SyncCadence;
use App\Jobs\SyncClimateSource;
use App\Models\SyncRun;
use Illuminate\Support\Facades\Cache;

/**
 * Use case: ask the climate sources that are overdue, without anybody pressing anything (ADR-0025).
 *
 * The traffic of the app is what drives it, so it has to be cheap: a cache stamp keeps it from
 * reading sync_runs on every single request, and a lock per source keeps ten visits at once from
 * queueing ten runs of the same thing.
 */
final class SyncDueSources
{
    /** How often the overdue check itself is worth doing. */
    private const CHECK_EVERY_SECONDS = 60;

    private const CHECK_KEY = 'climate-sync:checked-at';

    /**
     * @return list<string> The sources queued, for the tests and for logging.
     */
    public function __invoke(): array
    {
        // add() only succeeds for the first caller within the window: the rest return at once.
        if (! Cache::add(self::CHECK_KEY, true, self::CHECK_EVERY_SECONDS)) {
            return [];
        }

        $now = now();
        $lastRuns = SyncRun::query()
            ->selectRaw('source, max(started_at) as last_run')
            ->groupBy('source')
            ->pluck('last_run', 'source');

        $queued = [];

        foreach (SyncCadence::sources() as $source) {
            $lastRun = $lastRuns[$source] ?? null;

            if (! SyncCadence::isDue($source, $lastRun !== null ? $now->parse($lastRun) : null, $now)) {
                continue;
            }

            if (! $this->isWithinHours($source)) {
                continue;
            }

            // Held while the job runs, so a visit a second later does not queue the same source.
            if (! Cache::add("climate-sync:{$source}", true, SyncCadence::everyMinutes($source) * 60)) {
                continue;
            }

            SyncClimateSource::dispatch($source);
            $queued[] = $source;
        }

        return $queued;
    }

    /**
     * The local station only reports within its hours, in its own timezone (ADR-0016).
     */
    private function isWithinHours(string $source): bool
    {
        $timezone = config('services.weather_station.schedule_timezone', 'America/Bogota');
        $from = (int) substr((string) config('services.weather_station.schedule_from', '06:00'), 0, 2);
        $until = (int) substr((string) config('services.weather_station.schedule_until', '18:30'), 0, 2);

        return SyncCadence::isWithinHours($source, (int) now($timezone)->format('G'), $from, $until);
    }
}
