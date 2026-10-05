<?php

namespace App\Actions\Installers;

use App\Actions\SolarProjects\BuildConsumptionDiary;
use App\Actions\SolarProjects\SizeProjectSystem;
use App\Domain\Installers\QuoteRequestStatus;
use App\Models\QuoteRequest;

/**
 * Use case: one quote request, as the installer who received it reads it (ADR-0023).
 *
 * It carries the client's consumption diary (ADR-0013): the appliances they registered, space by
 * space, with what each one draws. That is the visit half done before anyone drives to the house,
 * and it is the promise the directory makes to the client in ADR-0022.
 */
final class DescribeQuoteRequest
{
    public function __construct(
        private readonly SizeProjectSystem $sizeProjectSystem,
        private readonly BuildConsumptionDiary $buildConsumptionDiary,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(QuoteRequest $quoteRequest): array
    {
        $project = $quoteRequest->solarProject->loadMissing(['user', 'municipality', 'technicalParameter', 'calculationResult']);
        $sized = ($this->sizeProjectSystem)($project);
        $parameters = $project->technicalParameter;
        // With the bill there is no diary to read (ADR-0020): the client only wrote the kWh.
        $diary = $project->usesBillConsumption() ? null : ($this->buildConsumptionDiary)($project);

        return [
            'quoteRequest' => $quoteRequest,
            'status' => QuoteRequestStatus::normalize($quoteRequest->status),
            'statusLabel' => QuoteRequestStatus::installerLabel($quoteRequest->status),
            'open' => $quoteRequest->isOpen(),
            'requestedAt' => $quoteRequest->created_at,
            'answeredAt' => $quoteRequest->answered_at,
            'contractValueCop' => $quoteRequest->contract_value_cop !== null ? (float) $quoteRequest->contract_value_cop : null,
            'note' => $quoteRequest->note,
            'clientName' => $project->user?->name,
            'clientEmail' => $project->user?->email,
            'project' => $project,
            'municipality' => $project->municipality?->name,
            'monthlyKwh' => $project->monthlyConsumption(),
            'usesBill' => $project->usesBillConsumption(),
            'diary' => $diary,
            'roofAreaM2' => $parameters !== null ? (float) $parameters->available_area_m2 : null,
            'panelPowerW' => $parameters !== null ? (float) $parameters->panel_power_w : null,
            'panels' => $sized['sizing']->panelsInstalled ?? null,
            'panelsThatFit' => $sized['sizing']->panelsThatFit ?? null,
            'roofIsEnough' => $sized !== null ? $sized['sizing']->roofIsEnough() : null,
            'coverage' => $sized !== null ? $sized['sizing']->coveragePercentage() : null,
            'fromClimateData' => $sized['fromClimateData'] ?? false,
            // Frozen when the client asked (ADR-0024); the project's current figure only for the
            // requests that are older than that rule.
            'budgetCop' => $quoteRequest->quoted_cost_cop !== null
                ? (float) $quoteRequest->quoted_cost_cop
                : ($project->estimated_installation_cost !== null ? (float) $project->estimated_installation_cost : null),
            'quotedPricePerKwCop' => $quoteRequest->quoted_price_per_kw_cop !== null ? (float) $quoteRequest->quoted_price_per_kw_cop : null,
            'budgetMovedSince' => $quoteRequest->quoted_cost_cop !== null
                && $project->estimated_installation_cost !== null
                && abs((float) $quoteRequest->quoted_cost_cop - (float) $project->estimated_installation_cost) >= 1,
        ];
    }
}
