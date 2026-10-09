<?php

namespace App\Actions\Installers;

use App\Domain\Installers\InstallerCoverage;
use App\Domain\Installers\QuoteComparison;
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
    public function __invoke(QuoteRequest $quoteRequest, ?int $rivalId = null): array
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
        $others = $this->others($quoteRequest);
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

            'others' => $others,
            // With this one plus the others there is a table worth opening (ADR-0028).
            'comparable' => count($others) + 1 >= QuoteComparison::MINIMUM,
            // This quote against one of the others, field by field (ADR-0029).
            'faceOff' => $this->faceOff($quoteRequest, $others, $annualSavings, $rivalId),
        ];
    }

    /**
     * This quote against another one, the way the client reads two offers: the price, what that
     * price covers, and nothing else. The whole table is one click away (ADR-0028).
     *
     * The rival is the one the client picked, or the cheapest of the rest by default. With two
     * quotes in the whole project the recommendation travels too, because then the head to head
     * and the full comparison are looking at exactly the same offers; with more, it would
     * contradict the table and it is left for the table.
     *
     * @param  list<array<string, mixed>>  $others
     * @return array<string, mixed>|null
     */
    private function faceOff(QuoteRequest $quoteRequest, array $others, ?float $annualSavings, ?int $rivalId): ?array
    {
        if ($others === []) {
            return null;
        }

        $chosen = null;

        foreach ($others as $other) {
            if ($other['id'] === $rivalId) {
                $chosen = $other;
            }
        }

        $chosen ??= $others[0];
        $rival = QuoteRequest::query()->with(['installer:id,name', 'installerQuote'])->find($chosen['id']);

        if ($rival?->installerQuote === null) {
            return null;
        }

        $mine = $quoteRequest->installerQuote;
        $comparison = QuoteComparison::faceOff(
            [
                $mine->comparisonValues($annualSavings),
                $rival->installerQuote->comparisonValues($annualSavings),
            ],
            $annualSavings !== null && $annualSavings > 0,
        );

        return [
            'rows' => $comparison['rows'],
            'verdict' => $comparison['verdict'],
            // Only when these two are every quote of the project does the head to head get to
            // recommend: otherwise it would name a winner the full table does not.
            'recommendation' => count($others) === 1 ? $comparison['recommendation'] : null,
            'mine' => [
                'name' => $quoteRequest->installer->name,
                'amountCop' => (float) $mine->amount_cop,
                'powerKw' => $mine->power_kw !== null ? (float) $mine->power_kw : null,
                'includesBattery' => (bool) $mine->includes_battery,
                'expired' => $mine->hasExpired(),
            ],
            'rival' => [
                'id' => $rival->id,
                'name' => $rival->installer->name,
                'amountCop' => (float) $rival->installerQuote->amount_cop,
                'powerKw' => $rival->installerQuote->power_kw !== null ? (float) $rival->installerQuote->power_kw : null,
                'includesBattery' => (bool) $rival->installerQuote->includes_battery,
                'expired' => $rival->installerQuote->hasExpired(),
            ],
            // The others to switch to, so the client can face this quote against each one in turn.
            'rivals' => array_map(fn (array $other): array => [
                'id' => $other['id'],
                'name' => $other['name'],
                'current' => $other['id'] === $chosen['id'],
            ], $others),
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
