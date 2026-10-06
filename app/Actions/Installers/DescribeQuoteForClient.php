<?php

namespace App\Actions\Installers;

use App\Domain\Installers\InstallerCoverage;
use App\Domain\Installers\QuoteRequestStatus;
use App\Domain\Solar\Profitability;
use App\Models\Municipality;
use App\Models\QuoteRequest;

/**
 * Use case: one installer's quote, as the client who asked for it reads it (ADR-0026).
 *
 * The card of the directory shows the price; this page answers what the price alone does not. With
 * the savings the project already estimated, the app recalculates how long *this* price takes to pay
 * for itself, and puts it next to the reference budget frozen the day it was asked (ADR-0024) and
 * next to what the other installers offered for the same roof.
 */
final class DescribeQuoteForClient
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(QuoteRequest $quoteRequest): array
    {
        $quoteRequest->loadMissing([
            'installerQuote',
            'installer.municipalities:id,name',
            'solarProject.calculationResult',
            'solarProject.municipality',
        ]);

        $quote = $quoteRequest->installerQuote;
        $project = $quoteRequest->solarProject;
        $result = $project->calculationResult;
        $installer = $quoteRequest->installer;

        $amount = $quote !== null ? (float) $quote->amount_cop : null;
        $annualSavings = $result?->estimated_annual_savings_cop !== null
            ? (float) $result->estimated_annual_savings_cop
            : null;
        $payback = $amount !== null && $annualSavings !== null
            ? Profitability::paybackYearsFor($amount, $annualSavings)
            : null;

        // What the app said it should cost around here, frozen the day they asked (ADR-0024).
        $reference = $quoteRequest->quoted_cost_cop !== null ? (float) $quoteRequest->quoted_cost_cop : null;
        $powerKw = $quote?->power_kw !== null ? (float) $quote->power_kw : null;

        return [
            'quoteRequest' => $quoteRequest,
            'quote' => $quote,
            'project' => $project,
            'installer' => $installer,
            'coverage' => InstallerCoverage::text(
                $installer->municipalities->pluck('name')->all(),
                Municipality::query()->active()->count(),
            ),
            'municipalityName' => $project->municipality?->name,
            'status' => QuoteRequestStatus::normalize($quoteRequest->status),
            'statusLabel' => QuoteRequestStatus::label($quoteRequest->status),
            'requestedAt' => $quoteRequest->created_at,
            'answeredAt' => $quoteRequest->answered_at,

            'amountCop' => $amount,
            'referenceCop' => $reference,
            // Negative means it is cheaper than the reference budget.
            'differenceCop' => $amount !== null && $reference !== null ? $amount - $reference : null,
            'powerKw' => $powerKw,
            // Per kW two quotes are comparable even when each proposes a different system.
            'pricePerKwCop' => $amount !== null && $powerKw !== null && $powerKw > 0 ? $amount / $powerKw : null,
            'referencePricePerKwCop' => $quoteRequest->quoted_price_per_kw_cop !== null
                ? (float) $quoteRequest->quoted_price_per_kw_cop
                : null,

            'annualSavingsCop' => $annualSavings,
            'monthlySavingsCop' => $annualSavings !== null ? $annualSavings / 12 : null,
            'paybackYears' => $payback,
            'profitability' => Profitability::fromPaybackYears($payback),
            // Without a calculation there are no savings, so there is no payback to show.
            'calculated' => $result !== null,

            'monthlyKwh' => $project->monthlyConsumption(),
            'requiredPowerKw' => $project->required_power_kw !== null ? (float) $project->required_power_kw : null,
            'coveragePercentage' => $result?->coverage_percentage !== null ? (float) $result->coverage_percentage : null,

            'others' => $this->others($quoteRequest),
        ];
    }

    /**
     * What the other installers offered for the same project: the comparison the client came for.
     * Only the ones that already answered with a price.
     *
     * @return list<array{name: string, amountCop: float, powerKw: float|null, includesBattery: bool, expired: bool, id: int}>
     */
    private function others(QuoteRequest $quoteRequest): array
    {
        return QuoteRequest::query()
            ->where('solar_project_id', $quoteRequest->solar_project_id)
            ->whereKeyNot($quoteRequest->getKey())
            ->whereHas('installerQuote')
            ->with(['installer:id,name', 'installerQuote'])
            ->get()
            ->map(fn (QuoteRequest $other): array => [
                'id' => $other->id,
                'name' => $other->installer->name,
                'amountCop' => (float) $other->installerQuote->amount_cop,
                'powerKw' => $other->installerQuote->power_kw !== null ? (float) $other->installerQuote->power_kw : null,
                'includesBattery' => (bool) $other->installerQuote->includes_battery,
                'expired' => $other->installerQuote->hasExpired(),
            ])
            ->sortBy('amountCop')
            ->values()
            ->all();
    }
}
