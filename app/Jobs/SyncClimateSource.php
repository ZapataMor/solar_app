<?php

namespace App\Jobs;

use App\Domain\Sync\SyncCadence;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Artisan;

/**
 * Runs one climate sync command in the background (ADR-0025).
 *
 * The command is the same one the scheduler calls, so it opens and closes its own SyncRun and the
 * health strip keeps telling the truth (ADR-0016).
 */
class SyncClimateSource implements ShouldQueue
{
    use Queueable;

    /** Ambient paces its requests one per second, and a long gap takes a few of them. */
    public int $timeout = 180;

    public int $tries = 1;

    public function __construct(public readonly string $source) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        // One run per source at a time, however many visits asked for it.
        return [(new WithoutOverlapping($this->source))->dontRelease()->expireAfter(300)];
    }

    public function handle(): void
    {
        Artisan::call(SyncCadence::command($this->source));
    }
}
