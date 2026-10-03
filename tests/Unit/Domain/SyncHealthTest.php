<?php

namespace Tests\Unit\Domain;

use App\Domain\Sync\ElapsedTime;
use App\Domain\Sync\SchedulerHeartbeat;
use App\Domain\Sync\SourceHealth;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class SyncHealthTest extends TestCase
{
    private function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable("2026-10-02 {$time}:00", new \DateTimeZone('UTC'));
    }

    public function test_a_source_with_recent_data_is_up_to_date(): void
    {
        $health = SourceHealth::evaluate($this->at('12:20'), $this->at('12:05'), 20, false);

        $this->assertSame(SourceHealth::OK, $health->status);
        $this->assertSame(15, $health->minutesBehind);
        $this->assertFalse($health->isProblem());
    }

    public function test_the_limit_itself_is_still_on_time(): void
    {
        $this->assertSame(SourceHealth::OK, SourceHealth::evaluate($this->at('12:20'), $this->at('12:00'), 20, false)->status);
        $this->assertSame(SourceHealth::LATE, SourceHealth::evaluate($this->at('12:21'), $this->at('12:00'), 20, false)->status);
    }

    public function test_old_data_is_late_and_a_failed_last_run_makes_it_failing(): void
    {
        $late = SourceHealth::evaluate($this->at('14:00'), $this->at('12:00'), 20, false);
        $failing = SourceHealth::evaluate($this->at('14:00'), $this->at('12:00'), 20, true);

        $this->assertSame(SourceHealth::LATE, $late->status);
        $this->assertSame(120, $late->minutesBehind);
        $this->assertSame(SourceHealth::FAILING, $failing->status);
        $this->assertTrue($late->isProblem() && $failing->isProblem());
    }

    public function test_an_error_with_fresh_data_is_not_a_problem(): void
    {
        // A passing 429: the data kept arriving, so there is nothing to alert about.
        $this->assertSame(SourceHealth::OK, SourceHealth::evaluate($this->at('12:10'), $this->at('12:05'), 20, true)->status);
    }

    public function test_a_source_without_data_is_empty_or_failing(): void
    {
        $this->assertSame(SourceHealth::EMPTY, SourceHealth::evaluate($this->at('12:00'), null, 20, false)->status);
        $this->assertFalse(SourceHealth::evaluate($this->at('12:00'), null, 20, false)->isProblem());
        $this->assertSame(SourceHealth::FAILING, SourceHealth::evaluate($this->at('12:00'), null, 20, true)->status);
    }

    public function test_a_source_with_hours_counts_its_age_from_when_it_opened(): void
    {
        // Opened at 06:00 with last night's data: at 06:20 it is not late yet, at 06:40 it is.
        $opened = $this->at('06:00');
        $yesterday = new DateTimeImmutable('2026-10-01 18:25:00', new \DateTimeZone('UTC'));

        $this->assertSame(SourceHealth::OK, SourceHealth::evaluate($this->at('06:20'), $yesterday, 30, false, $opened)->status);
        $this->assertSame(SourceHealth::LATE, SourceHealth::evaluate($this->at('06:40'), $yesterday, 30, false, $opened)->status);
        // Data newer than the opening counts as it is.
        $this->assertSame(10, SourceHealth::evaluate($this->at('06:40'), $this->at('06:30'), 30, false, $opened)->minutesBehind);
    }

    public function test_outside_its_hours_nothing_is_expected(): void
    {
        $health = SourceHealth::evaluate($this->at('23:00'), $this->at('12:00'), 30, true, $this->at('06:00'), paused: true);

        $this->assertSame(SourceHealth::IDLE, $health->status);
        $this->assertFalse($health->isProblem());
    }

    public function test_the_scheduler_stops_after_ten_minutes_without_a_beat(): void
    {
        $this->assertSame(SchedulerHeartbeat::BEATING, SchedulerHeartbeat::evaluate($this->at('12:10'), $this->at('12:00'))->status);

        $stopped = SchedulerHeartbeat::evaluate($this->at('12:11'), $this->at('12:00'));
        $this->assertSame(SchedulerHeartbeat::STOPPED, $stopped->status);
        $this->assertSame(11, $stopped->minutesSinceBeat);
        $this->assertTrue($stopped->isProblem());
    }

    public function test_a_scheduler_that_never_beat_is_unknown_not_a_problem(): void
    {
        $health = SchedulerHeartbeat::evaluate($this->at('12:00'), null);

        $this->assertSame(SchedulerHeartbeat::UNKNOWN, $health->status);
        $this->assertFalse($health->isProblem());
    }

    public function test_elapsed_time_reads_in_spanish(): void
    {
        $this->assertSame('hace un momento', ElapsedTime::ago(0));
        $this->assertSame('hace 5 min', ElapsedTime::ago(5));
        $this->assertSame('hace 2 h', ElapsedTime::ago(130));
        $this->assertSame('hace 1 día', ElapsedTime::ago(60 * 24));
        $this->assertSame('hace 3 días', ElapsedTime::ago(60 * 24 * 3 + 50));
        $this->assertSame('2 h', ElapsedTime::lapse(120));
    }
}
