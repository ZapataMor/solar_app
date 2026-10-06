<?php

namespace Tests\Unit\Domain;

use App\Domain\Solar\Profitability;
use PHPUnit\Framework\TestCase;

/**
 * ADR-0026: a real quote is judged by how long it takes to pay for itself, not by how it compares
 * with the reference budget.
 */
class QuotePaybackTest extends TestCase
{
    public function test_a_price_pays_itself_with_the_savings_of_the_project(): void
    {
        // 18 millones con 3 de ahorro al año: seis años.
        $this->assertSame(6.0, Profitability::paybackYearsFor(18_000_000, 3_000_000));
        $this->assertSame(4.5, Profitability::paybackYearsFor(18_000_000, 4_000_000));
    }

    public function test_without_savings_or_without_price_there_is_no_payback(): void
    {
        $this->assertNull(Profitability::paybackYearsFor(18_000_000, 0));
        $this->assertNull(Profitability::paybackYearsFor(0, 3_000_000));
    }

    public function test_the_verdict_follows_the_payback_of_that_price(): void
    {
        $this->assertSame(Profitability::GOOD, Profitability::fromPaybackYears(
            Profitability::paybackYearsFor(15_000_000, 3_000_000),
        )->level);
        // El mismo sistema, más caro, deja de ser rentable.
        $this->assertSame(Profitability::POOR, Profitability::fromPaybackYears(
            Profitability::paybackYearsFor(40_000_000, 3_000_000),
        )->level);
    }
}
