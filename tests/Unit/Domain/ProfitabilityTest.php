<?php

namespace Tests\Unit\Domain;

use App\Domain\Solar\Profitability;
use PHPUnit\Framework\TestCase;

class ProfitabilityTest extends TestCase
{
    public function test_the_payback_decides_the_label(): void
    {
        $this->assertSame(['good', 'Rentable'], $this->verdict(2.5));
        $this->assertSame(['good', 'Rentable'], $this->verdict(6));
        $this->assertSame(['fair', 'Retorno medio'], $this->verdict(8));
        $this->assertSame(['poor', 'Poco rentable'], $this->verdict(14));
        // Savings that never pay for the installation.
        $this->assertSame(['none', 'No rentable'], $this->verdict(null));
        $this->assertNull(Profitability::fromPaybackYears(null)->paybackYears);
    }

    public function test_the_payback_reads_in_years_and_months(): void
    {
        $this->assertSame('2 años y 6 meses', Profitability::paybackText(2.5));
        $this->assertSame('1 año', Profitability::paybackText(1.02));
        $this->assertSame('3 años', Profitability::paybackText(2.98));
        $this->assertSame('8 meses', Profitability::paybackText(0.66));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function verdict(?float $paybackYears): array
    {
        $profitability = Profitability::fromPaybackYears($paybackYears);

        return [$profitability->level, $profitability->label()];
    }
}
