<?php

namespace App\Actions\Installers;

use App\Domain\Installers\QuoteComparison;
use App\Domain\Installers\QuoteInclusions;
use App\Domain\Installers\QuoteSystem;
use App\Domain\Solar\Profitability;
use App\Models\InstallerQuote;
use App\Models\QuoteRequest;
use App\Models\SolarProject;
use Illuminate\Support\Collection;

/**
 * Use case: the quotes of one project side by side (ADR-0028).
 *
 * Until now each quote was read on its own, and comparing meant opening one, remembering it, going
 * back and opening the next. Here the columns are the installers and the rows are the fields of
 * ADR-0027, so the same field is read across every offer at once.
 *
 * The screen only exists with two quotes or more: with one there is nothing to compare, and this
 * returns null so the controller answers 404 and the client stays on the detail page.
 *
 * Who decides is the client. The app marks the best of each row (that is `QuoteComparison`) and
 * nothing else: no score, no ranking, no recommendation.
 */
final class CompareProjectQuotes
{
    /**
     * @return array<string, mixed>|null
     */
    public function __invoke(SolarProject $solarProject): ?array
    {
        $requests = QuoteRequest::query()
            ->where('solar_project_id', $solarProject->getKey())
            ->whereHas('installerQuote')
            ->with(['installer', 'installerQuote'])
            ->get();

        if ($requests->count() < QuoteComparison::MINIMUM) {
            return null;
        }

        $solarProject->loadMissing(['calculationResult', 'municipality']);
        $result = $solarProject->calculationResult;
        $annualSavings = $result?->estimated_annual_savings_cop !== null
            ? (float) $result->estimated_annual_savings_cop
            : null;

        // Savings of zero are as useless as no savings at all: `paybackYearsFor` answers null for
        // both, and a payback row full of "no lo dice" would blame the installers for a number only
        // the app works out. The screen says why itself, with the way to fix it.
        $payback = $annualSavings !== null && $annualSavings > 0;

        $columns = $this->columns($requests, $payback ? $annualSavings : null);
        $comparison = QuoteComparison::of($columns->pluck('values')->all(), $payback);

        return [
            'project' => $solarProject,
            'municipalityName' => $solarProject->municipality?->name,
            'monthlyKwh' => $solarProject->monthlyConsumption(),
            'requiredPowerKw' => $solarProject->required_power_kw !== null
                ? (float) $solarProject->required_power_kw
                : null,
            // What the app said it should cost around here (ADR-0024): a ruler, not a quote.
            'referenceCop' => $solarProject->estimated_installation_cost > 0
                ? (float) $solarProject->estimated_installation_cost
                : null,
            'annualSavingsCop' => $payback ? $annualSavings : null,
            // Not "has a calculation" but "has savings to measure this price against": a project
            // calculated with no savings cannot answer the payback either.
            'calculated' => $payback,
            'columns' => $columns->all(),
            'groups' => $comparison['groups'],
            'silent' => $comparison['silent'],
            'caveats' => $comparison['caveats'],
        ];
    }

    /**
     * One column per quote, cheapest first. An expired price is not a price (ADR-0026), so it goes
     * last whatever figure it carries: it is kept because the client asked for it.
     *
     * @param  Collection<int, QuoteRequest>  $requests
     * @return Collection<int, array<string, mixed>>
     */
    private function columns(Collection $requests, ?float $annualSavings): Collection
    {
        return $requests
            ->map(fn (QuoteRequest $request): array => $this->column($request, $annualSavings))
            ->sortBy(fn (array $column): array => [$column['expired'] ? 1 : 0, $column['amountCop']])
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function column(QuoteRequest $request, ?float $annualSavings): array
    {
        /** @var InstallerQuote $quote */
        $quote = $request->installerQuote;
        $installer = $request->installer;

        $amount = (float) $quote->amount_cop;
        $powerKw = $quote->power_kw !== null ? (float) $quote->power_kw : null;
        $expired = $quote->hasExpired();

        return [
            'quoteRequestId' => $request->id,
            'installerName' => $installer->name,
            'installerTagline' => $installer->tagline,
            'yearsExperience' => $installer->years_experience,
            'whatsapp' => $installer->whatsappNumber(),
            'phone' => $installer->phone,
            'statusLabel' => $request->statusLabel(),
            // On the header, next to the name: a total without VAT is not comparable to one with it.
            'vatIncluded' => $quote->vat_included,
            'amountCop' => $amount,
            'expired' => $expired,
            'validUntil' => $quote->valid_until,
            'daysLeft' => $quote->daysLeft(),
            'missesLegalization' => $quote->missesLegalization(),
            'scope' => $quote->scope,
            'exclusions' => $quote->exclusions,
            // The cells, as the domain compares them: primitives only, with the keys of the rows.
            // The inclusion keys are the ones of the shared catalogue (ADR-0027), so they keep their
            // snake_case next to the camelCase of the rest instead of being renamed here.
            'values' => [
                'amountCop' => $amount,
                'powerKw' => $powerKw,
                // Per kW two quotes are comparable even when each proposes a different system.
                'pricePerKwCop' => $powerKw !== null && $powerKw > 0 ? $amount / $powerKw : null,
                'paybackYears' => $annualSavings !== null
                    ? Profitability::paybackYearsFor($amount, $annualSavings)
                    : null,
                QuoteInclusions::RETIE => (bool) $quote->includes_retie,
                QuoteInclusions::GRID_PAPERWORK => (bool) $quote->includes_grid_paperwork,
                QuoteInclusions::BIDIRECTIONAL_METER => (bool) $quote->includes_bidirectional_meter,
                QuoteInclusions::BATTERY => (bool) $quote->includes_battery,
                QuoteInclusions::MAINTENANCE => (bool) $quote->includes_maintenance,
                'panelWarrantyYears' => $quote->panel_warranty_years,
                'inverterWarrantyYears' => $quote->inverter_warranty_years,
                'workmanshipWarrantyYears' => $quote->workmanship_warranty_years,
                'downPaymentPercentage' => $quote->down_payment_percentage,
                'deliveryDays' => $quote->delivery_days,
                'vatIncluded' => $quote->vat_included,
                'validUntil' => $quote->valid_until,
                'daysLeft' => $quote->daysLeft(),
                'panelText' => $quote->panelText(),
                'inverterModel' => $quote->inverter_model,
                'batteryText' => QuoteSystem::batteryText(
                    (bool) $quote->includes_battery,
                    $quote->battery_kwh !== null ? (float) $quote->battery_kwh : null,
                ),
                'monthlyGenerationKwh' => $quote->monthly_generation_kwh !== null
                    ? (float) $quote->monthly_generation_kwh
                    : null,
                'expired' => $expired,
            ],
        ];
    }
}
