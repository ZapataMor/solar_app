<?php

namespace App\Actions\Installers;

use App\Domain\Installers\InstallerCoverage;
use App\Domain\Installers\QuoteNotPossible;
use App\Domain\Installers\QuoteRequestStatus;
use App\Models\Installer;
use App\Models\QuoteRequest;
use App\Models\SolarProject;

/**
 * Use case: a client asks an installer to quote a project (ADR-0022). The row is the lead of
 * ADR-0005; asking twice keeps the first one, so neither the date of the contact nor the budget
 * it was asked with (ADR-0024) move.
 */
final class RequestInstallerQuote
{
    public function __invoke(SolarProject $solarProject, Installer $installer, ?string $note = null): QuoteRequest
    {
        if (! $installer->active) {
            throw QuoteNotPossible::notAvailable();
        }

        // The installer quotes over an estimate: without consumption there is nothing to quote.
        if ($solarProject->monthlyConsumption() <= 0) {
            throw QuoteNotPossible::withoutConsumption();
        }

        $covered = InstallerCoverage::covers(
            $installer->municipalities()->pluck('municipalities.id')->all(),
            $solarProject->municipality_id,
        );

        if (! $covered) {
            throw QuoteNotPossible::outsideCoverage();
        }

        return QuoteRequest::query()->firstOrCreate(
            ['solar_project_id' => $solarProject->id, 'installer_id' => $installer->id],
            [
                'status' => QuoteRequestStatus::SENT,
                'note' => $note,
                // Frozen here (ADR-0024): if the price of the municipality changes later, what the
                // two of them are talking about does not.
                'quoted_cost_cop' => $solarProject->estimated_installation_cost,
                'quoted_price_per_kw_cop' => $solarProject->final_price_per_kw_used,
            ],
        );
    }
}
