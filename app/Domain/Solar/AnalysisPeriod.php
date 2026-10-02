<?php

namespace App\Domain\Solar;

use DateTimeImmutable;

/**
 * The days of climate data a project is sized with. A single day is not enough: a cloudy day, or
 * today with only its morning measured, turned into thousands of panels. By default it is the last
 * three months up to today; it must cover at least a whole month and never end after today.
 */
final class AnalysisPeriod
{
    public const DEFAULT_MONTHS = 3;

    public const MINIMUM_MONTHS = 1;

    /** Start of the default period that ends on $end (today when creating): 2 Oct → 2 Jul. */
    public static function defaultStart(DateTimeImmutable $end): DateTimeImmutable
    {
        return self::monthsBefore(self::day($end), self::DEFAULT_MONTHS);
    }

    /** The latest start that still covers a whole month up to $end, both days included: 31 Mar → 1 Mar. */
    public static function latestStart(DateTimeImmutable $end): DateTimeImmutable
    {
        return self::monthsBefore(self::day($end)->modify('+1 day'), self::MINIMUM_MONTHS);
    }

    public static function isLongEnough(DateTimeImmutable $start, DateTimeImmutable $end): bool
    {
        return self::day($start) <= self::latestStart($end);
    }

    /** Calendar months back without overflow: 31 May minus three months is 28 February, not 3 March. */
    private static function monthsBefore(DateTimeImmutable $date, int $months): DateTimeImmutable
    {
        $month = $date->modify('first day of this month')->modify("-{$months} months");
        $day = min((int) $date->format('j'), (int) $month->format('t'));

        return $month->setDate((int) $month->format('Y'), (int) $month->format('n'), $day);
    }

    private static function day(DateTimeImmutable $date): DateTimeImmutable
    {
        return $date->setTime(0, 0);
    }
}
