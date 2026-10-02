<?php

namespace App\Actions\SolarProjects;

use App\Domain\Solar\Profitability;
use App\Models\SolarProject;

/**
 * Use case: the figures a portfolio card and the portfolio table show for a project: what it costs,
 * when it pays for itself and whether it is a good investment.
 */
final class SummarizePortfolioProject
{
    /**
     * @return array{calculated: bool, costCop: float|null, paybackYears: float|null, paybackText: string|null, profitability: Profitability|null}
     */
    public function __invoke(SolarProject $solarProject): array
    {
        $result = $solarProject->calculationResult;

        if ($result === null) {
            // Not calculated yet: the quote by municipality is already the cost; payback needs the sun.
            return [
                'calculated' => false,
                'costCop' => $solarProject->estimated_installation_cost !== null ? (float) $solarProject->estimated_installation_cost : null,
                'paybackYears' => null,
                'paybackText' => null,
                'profitability' => null,
            ];
        }

        $profitability = Profitability::fromPaybackYears($result->payback_period_years !== null ? (float) $result->payback_period_years : null);

        return [
            'calculated' => true,
            'costCop' => (float) $result->installation_cost_cop,
            'paybackYears' => $profitability->paybackYears,
            'paybackText' => $profitability->paybackYears !== null ? Profitability::paybackText($profitability->paybackYears) : 'No se recupera',
            'profitability' => $profitability,
        ];
    }
}
