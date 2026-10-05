<?php

namespace App\Actions\Installers;

use App\Domain\Installers\QuoteRequestStatus;
use App\Models\Installer;
use App\Models\QuoteRequest;

/**
 * Use case: the list of quote requests one installer received (ADR-0023). Only what tells them which
 * one to open; everything about a request lives on its own page (DescribeQuoteRequest).
 *
 * Open ones come first, and inside each group the newest: what waits for an answer is the reason to
 * open this screen.
 */
final class DescribeInstallerInbox
{
    /**
     * @return array{requests: list<array<string, mixed>>, open: int, won: int, contractsCop: float}
     */
    public function __invoke(Installer $installer): array
    {
        $requests = QuoteRequest::query()
            ->where('installer_id', $installer->id)
            ->with(['solarProject.user:id,name', 'solarProject.municipality:id,name'])
            ->get()
            ->sortBy(fn (QuoteRequest $request) => [$request->isOpen() ? 0 : 1, -$request->created_at->timestamp])
            ->values();

        return [
            'requests' => $requests->map(function (QuoteRequest $request): array {
                $project = $request->solarProject;

                return [
                    'id' => $request->id,
                    'status' => QuoteRequestStatus::normalize($request->status),
                    'statusLabel' => QuoteRequestStatus::installerLabel($request->status),
                    'open' => $request->isOpen(),
                    'requestedAt' => $request->created_at,
                    'contractValueCop' => $request->contract_value_cop !== null ? (float) $request->contract_value_cop : null,
                    'clientName' => $project->user?->name,
                    'projectName' => $project->name,
                    'propertyType' => $project->property_type,
                    'municipality' => $project->municipality?->name,
                    'monthlyKwh' => $project->monthlyConsumption(),
                ];
            })->all(),
            'open' => $requests->filter(fn (QuoteRequest $request) => $request->isOpen())->count(),
            'won' => $requests->where('status', QuoteRequestStatus::WON)->count(),
            'contractsCop' => (float) $requests->sum(fn (QuoteRequest $request) => (float) $request->contract_value_cop),
        ];
    }
}
