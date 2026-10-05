<?php

namespace App\Domain\Sync;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * How often each climate source should be asked, and whether it is overdue (ADR-0025).
 *
 * The same cadence the scheduler uses in routes/console.php. It lives here so the traffic-driven
 * fallback and the scheduler cannot drift apart, and so the rule can be tested without a clock.
 */
final class SyncCadence
{
    public const AMBIENT = 'ambient';

    public const LOCAL = 'local';

    public const NASA = 'nasa_power';

    /** Minutes between runs of each source. */
    private const EVERY = [
        self::AMBIENT => 5,
        self::LOCAL => 5,
        // NASA publishes once a day; every six hours re-checks the window (ADR-0009).
        self::NASA => 360,
    ];

    /** The artisan command each source runs. */
    private const COMMANDS = [
        self::AMBIENT => 'ambient:sync',
        self::LOCAL => 'weather-station:fetch',
        self::NASA => 'nasa-power:fetch',
    ];

    /**
     * @return list<string>
     */
    public static function sources(): array
    {
        return array_keys(self::EVERY);
    }

    public static function command(string $source): string
    {
        return self::COMMANDS[$source] ?? throw new \InvalidArgumentException("Fuente desconocida: {$source}");
    }

    public static function everyMinutes(string $source): int
    {
        return self::EVERY[$source] ?? throw new \InvalidArgumentException("Fuente desconocida: {$source}");
    }

    /**
     * A source never run is due: it is the first visit after a deploy, or after the table was pruned.
     */
    public static function isDue(string $source, ?DateTimeInterface $lastRunAt, DateTimeInterface $now): bool
    {
        if ($lastRunAt === null) {
            return true;
        }

        $next = DateTimeImmutable::createFromInterface($lastRunAt)
            ->modify('+'.self::everyMinutes($source).' minutes');

        return $now >= $next;
    }

    /**
     * The local station only reports within its hours (ADR-0016): asking at three in the morning
     * burns a request to be told the same thing.
     *
     * @param  int  $hour  Hour of the day where the station is, 0–23.
     */
    public static function isWithinHours(string $source, int $hour, int $fromHour, int $untilHour): bool
    {
        if ($source !== self::LOCAL) {
            return true;
        }

        return $hour >= $fromHour && $hour <= $untilHour;
    }
}
