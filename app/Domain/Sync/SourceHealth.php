<?php

namespace App\Domain\Sync;

use DateTimeImmutable;

/**
 * How up to date a climate source is (ADR-0016): the age of its newest data against what its
 * schedule promises, and whether its last run failed.
 */
final class SourceHealth
{
    public const OK = 'ok';

    /** Newer data is overdue, with no error known. */
    public const LATE = 'late';

    /** Newer data is overdue and the last run ended in error. */
    public const FAILING = 'failing';

    /** Outside the hours the source is scheduled to run. */
    public const IDLE = 'idle';

    /** No data yet. */
    public const EMPTY = 'empty';

    public function __construct(
        public readonly string $status,
        public readonly ?int $minutesBehind,
    ) {}

    /**
     * @param  int  $lateAfterMinutes  Overdue once the newest data is older than this.
     * @param  DateTimeImmutable|null  $expectedSince  A source that only runs part of the day is not late
     *                                                 for the hours it was off: its age counts from when it opened.
     * @param  bool  $paused  Outside its schedule: nothing is expected now.
     */
    public static function evaluate(
        DateTimeImmutable $now,
        ?DateTimeImmutable $lastDataAt,
        int $lateAfterMinutes,
        bool $lastRunFailed,
        ?DateTimeImmutable $expectedSince = null,
        bool $paused = false,
    ): self {
        if ($paused) {
            return new self(self::IDLE, null);
        }

        if ($lastDataAt === null) {
            return new self($lastRunFailed ? self::FAILING : self::EMPTY, null);
        }

        $since = $expectedSince !== null && $lastDataAt < $expectedSince ? $expectedSince : $lastDataAt;

        $behind = max(0, intdiv($now->getTimestamp() - $since->getTimestamp(), 60));

        if ($behind <= $lateAfterMinutes) {
            return new self(self::OK, $behind);
        }

        return new self($lastRunFailed ? self::FAILING : self::LATE, $behind);
    }

    public function isProblem(): bool
    {
        return in_array($this->status, [self::LATE, self::FAILING], true);
    }
}
