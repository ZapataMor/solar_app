<?php

namespace Tests\Unit\Domain;

use App\Domain\Sync\SyncCadence;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * ADR-0025: when each climate source is overdue, with the same cadence the scheduler uses.
 */
class SyncCadenceTest extends TestCase
{
    public function test_a_source_is_due_once_its_interval_passed(): void
    {
        $now = new DateTimeImmutable('2026-10-05 12:00:00');

        $this->assertFalse(SyncCadence::isDue(SyncCadence::AMBIENT, new DateTimeImmutable('2026-10-05 11:56:00'), $now));
        $this->assertTrue(SyncCadence::isDue(SyncCadence::AMBIENT, new DateTimeImmutable('2026-10-05 11:55:00'), $now));

        // NASA publishes once a day; six hours apart is enough (ADR-0009).
        $this->assertFalse(SyncCadence::isDue(SyncCadence::NASA, new DateTimeImmutable('2026-10-05 07:00:00'), $now));
        $this->assertTrue(SyncCadence::isDue(SyncCadence::NASA, new DateTimeImmutable('2026-10-05 06:00:00'), $now));
    }

    public function test_a_source_that_never_ran_is_due(): void
    {
        $this->assertTrue(SyncCadence::isDue(SyncCadence::AMBIENT, null, new DateTimeImmutable('2026-10-05 12:00:00')));
    }

    public function test_only_the_local_station_keeps_hours(): void
    {
        // It reports between six in the morning and half past six in the evening (ADR-0016).
        $this->assertFalse(SyncCadence::isWithinHours(SyncCadence::LOCAL, 3, 6, 18));
        $this->assertTrue(SyncCadence::isWithinHours(SyncCadence::LOCAL, 6, 6, 18));
        $this->assertTrue(SyncCadence::isWithinHours(SyncCadence::LOCAL, 18, 6, 18));
        $this->assertFalse(SyncCadence::isWithinHours(SyncCadence::LOCAL, 19, 6, 18));

        // Ambient reports at night too, and NASA is a satellite.
        $this->assertTrue(SyncCadence::isWithinHours(SyncCadence::AMBIENT, 3, 6, 18));
        $this->assertTrue(SyncCadence::isWithinHours(SyncCadence::NASA, 3, 6, 18));
    }

    public function test_each_source_names_the_command_the_scheduler_runs(): void
    {
        $this->assertSame('ambient:sync', SyncCadence::command(SyncCadence::AMBIENT));
        $this->assertSame('weather-station:fetch', SyncCadence::command(SyncCadence::LOCAL));
        $this->assertSame('nasa-power:fetch', SyncCadence::command(SyncCadence::NASA));
    }
}
