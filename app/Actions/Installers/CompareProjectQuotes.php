<?php

namespace App\Actions\Installers;

use App\Domain\Installers\QuoteComparison;
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
     * @param  list<int>  $chosen  the quote requests the client put in the comparison; empty means
     *                             all of them, and a choice that leaves fewer than two is ignored
     *                             because one column compares nothing.
     * @return array<string, mixed>|null
     */
    public function __invoke(SolarProject $solarProject, array $chosen = []): ?array
    {
        $requests = QuoteRequest::query()
            ->where('solar_project_id', $solarProject->getKey())
            ->whereHas('installerQuote')
            ->with(['installer', 'installerQuote'])
            ->get();

        if ($requests->count() < QuoteComparison::MINIMUM) {
            return null;
        }

        $picked = $requests->whereIn('id', $chosen);
        $requests = $picked->count() >= QuoteComparison::MINIMUM ? $picked->values() : $requests;

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
            // Con dos, la pantalla se lee enfrentada en vez de en columnas (ADR-0029): es el mismo
            // contenido, con la etiqueta en el medio, que es como se lee un cara a cara.
            'faceOff' => $columns->count() === 2,
            // Everything the client could put side by side, to tick or untick (ADR-0029).
            'available' => $this->available($solarProject, $columns->pluck('quoteRequestId')->all()),
            'groups' => $comparison['groups'],
            'silent' => $comparison['silent'],
            'caveats' => $comparison['caveats'],
            // Two counts per column and the one the app recommends, with its reasons (ADR-0029).
            'verdict' => $comparison['verdict'],
            'recommendation' => $comparison['recommendation'],
        ];
    }

    /**
     * Every answered quote of the project, with whether it is in the comparison right now. It is a
     * second query on purpose: the list of what can be compared must not shrink with the choice,
     * or unticking one would make it disappear from the picker.
     *
     * @param  list<int>  $chosen
     * @return list<array{id: int, name: string, amountCop: float, expired: bool, chosen: bool}>
     */
    private function available(SolarProject $solarProject, array $chosen): array
    {
        return QuoteRequest::query()
            ->where('solar_project_id', $solarProject->getKey())
            ->whereHas('installerQuote')
            ->with(['installer:id,name', 'installerQuote'])
            ->get()
            ->map(fn (QuoteRequest $request): array => [
                'id' => $request->id,
                'name' => $request->installer->name,
                'amountCop' => (float) $request->installerQuote->amount_cop,
                'expired' => $request->installerQuote->hasExpired(),
                'chosen' => in_array($request->id, $chosen, true),
            ])
            ->sortBy([['expired', 'asc'], ['amountCop', 'asc']])
            ->values()
            ->all();
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
            // The cells, as the domain compares them: the map lives in the model, shared with
            // the head to head of the detail page (ADR-0029).
            'values' => $quote->comparisonValues($annualSavings),
        ];
    }
}
