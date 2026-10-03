<?php

namespace App\Domain\Sync;

use DateTimeImmutable;

/**
 * Whether the server's scheduler is running (ADR-0016). It leaves a beat every minute; if the
 * last one is older than a few minutes, the cron that calls `schedule:run` has stopped.
 */
final class SchedulerHeartbeat
{
    public const BEATING = 'beating';

    public const STOPPED = 'stopped';

    /** No beat recorded yet: a new deployment, or the cron was never set up. */
    public const UNKNOWN = 'unknown';

    public const STOPPED_AFTER_MINUTES = 10;

    public function __construct(
        public readonly string $status,
        public readonly ?int $minutesSinceBeat,
    ) {}

    public static function evaluate(DateTimeImmutable $now, ?DateTimeImmutable $lastBeatAt): self
    {
        if ($lastBeatAt === null) {
            return new self(self::UNKNOWN, null);
        }

        $minutes = max(0, intdiv($now->getTimestamp() - $lastBeatAt->getTimestamp(), 60));

        return new self($minutes > self::STOPPED_AFTER_MINUTES ? self::STOPPED : self::BEATING, $minutes);
    }

    public function isProblem(): bool
    {
        return $this->status === self::STOPPED;
    }
}
