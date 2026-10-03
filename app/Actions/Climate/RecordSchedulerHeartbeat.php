<?php

namespace App\Actions\Climate;

use Illuminate\Support\Facades\Cache;

/**
 * Use case: the scheduler leaves a beat every minute (routes/console.php), so the climate data page
 * can tell when the server's cron stopped calling `schedule:run` (ADR-0016).
 */
final class RecordSchedulerHeartbeat
{
    public const CACHE_KEY = 'scheduler.last_beat';

    public function __invoke(): void
    {
        Cache::forever(self::CACHE_KEY, now()->getTimestamp());
    }
}
