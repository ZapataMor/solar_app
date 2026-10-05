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
 * ADR-0005; asking twice keeps the first one, so the date of the contact does not move.
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
            ['status' => QuoteRequestStatus::SENT, 'note' => $note],
        );
    }
}
