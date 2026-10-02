<?php

namespace Tests\Unit\Domain;

use App\Domain\Solar\AnalysisPeriod;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AnalysisPeriodTest extends TestCase
{
    public function test_the_default_period_is_the_last_three_months(): void
    {
        $this->assertSame('2026-07-02', AnalysisPeriod::defaultStart(new DateTimeImmutable('2026-10-02 15:40'))->format('Y-m-d'));
    }

    public function test_months_back_do_not_overflow_into_the_next_month(): void
    {
        // 31 May minus three months is the end of February, not 3 March.
        $this->assertSame('2026-02-28', AnalysisPeriod::defaultStart(new DateTimeImmutable('2026-05-31'))->format('Y-m-d'));
        $this->assertSame('2025-10-31', AnalysisPeriod::defaultStart(new DateTimeImmutable('2026-01-31'))->format('Y-m-d'));
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function periods(): array
    {
        return [
            'a single day' => ['2026-10-02', '2026-10-02', false],
            'two weeks' => ['2026-09-18', '2026-10-02', false],
            'one day short of a month' => ['2026-09-04', '2026-10-02', false],
            'a whole month, both days included' => ['2026-09-03', '2026-10-02', true],
            'the whole of March' => ['2026-03-01', '2026-03-31', true],
            'the whole of February' => ['2026-02-01', '2026-02-28', true],
            'February, missing its last day' => ['2026-02-01', '2026-02-27', false],
            'the default three months' => ['2026-07-02', '2026-10-02', true],
            'reversed' => ['2026-10-02', '2026-07-02', false],
        ];
    }

    #[DataProvider('periods')]
    public function test_a_period_must_cover_at_least_a_whole_month(string $start, string $end, bool $longEnough): void
    {
        $this->assertSame($longEnough, AnalysisPeriod::isLongEnough(new DateTimeImmutable($start), new DateTimeImmutable($end)));
    }

    public function test_the_latest_start_still_covers_a_month(): void
    {
        $this->assertSame('2026-09-03', AnalysisPeriod::latestStart(new DateTimeImmutable('2026-10-02'))->format('Y-m-d'));
        $this->assertSame('2026-03-01', AnalysisPeriod::latestStart(new DateTimeImmutable('2026-03-31'))->format('Y-m-d'));
    }
}
