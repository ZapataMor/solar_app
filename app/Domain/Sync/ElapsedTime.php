<?php

namespace App\Domain\Sync;

/**
 * "hace 5 min", "hace 3 h", "hace 2 días": how long ago, in words the administrator reads at a glance.
 */
final class ElapsedTime
{
    public static function ago(int $minutes): string
    {
        return match (true) {
            $minutes < 1 => 'hace un momento',
            $minutes < 60 => "hace {$minutes} min",
            $minutes < 60 * 24 => 'hace '.intdiv($minutes, 60).' h',
            default => 'hace '.($days = intdiv($minutes, 60 * 24)).($days === 1 ? ' día' : ' días'),
        };
    }

    /** "2 h", "35 min": a lapse without "hace", for "lleva 2 h sin datos nuevos". */
    public static function lapse(int $minutes): string
    {
        return substr(self::ago($minutes), 5);
    }
}
