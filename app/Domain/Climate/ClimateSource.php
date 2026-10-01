<?php

namespace App\Domain\Climate;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Port for any provider of solar irradiance (NASA POWER, local station, Ambient Weather…).
 *
 * Adding a new station means writing one adapter that implements this
 * interface and registering it in AppServiceProvider; nothing else changes.
 */
interface ClimateSource
{
    public const AMBIENT = 'ambient';

    public const LOCAL = 'local';

    public const NASA_POWER = 'nasa_power';

    public function key(): string;

    public function label(): string;

    public function dailyIrradiance(DateTimeInterface $start, DateTimeInterface $end): ClimateSeries;

    /**
     * When this source's data inside the range last changed (new or updated rows), or null if it has none.
     */
    public function lastChangedAt(DateTimeInterface $start, DateTimeInterface $end): ?DateTimeImmutable;
}
