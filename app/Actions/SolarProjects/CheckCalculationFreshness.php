<?php

namespace App\Actions\SolarProjects;

use App\Domain\Climate\ClimateSourceChain;
use App\Domain\Solar\CalculationFreshness;
use App\Domain\Solar\CalculationFreshnessPolicy;
use App\Models\SolarProject;
use Carbon\CarbonImmutable;

/**
 * Use case: tell whether a project's calculation is up to date, and why not.
 */
final class CheckCalculationFreshness
{
    public function __construct(
        private readonly ClimateSourceChain $climateSources,
        private readonly CalculationFreshnessPolicy $policy,
    ) {}

    public function __invoke(SolarProject $solarProject): CalculationFreshness
    {
        if ($solarProject->start_date === null || $solarProject->end_date === null) {
            return new CalculationFreshness(CalculationFreshness::NOT_READY, ['El proyecto no tiene periodo de análisis.']);
        }

        // Appliances are the base of the calculation (ADR-0013): without consumption it cannot run yet.
        if ($solarProject->monthlyConsumption() <= 0) {
            return new CalculationFreshness(CalculationFreshness::NOT_READY, ['Agrega tus equipos en la pestaña Consumo para calcular.']);
        }

        $solarProject->loadMissing(['calculationResult', 'technicalParameter']);

        $start = $solarProject->start_date->copy()->startOfDay();
        $end = $solarProject->end_date->copy()->endOfDay();
        $priority = [];
        $labels = [];
        $changes = [];

        foreach ($this->climateSources->all() as $source) {
            $priority[] = $source->key();
            $labels[$source->key()] = $source->label();
            $changes[$source->key()] = $source->lastChangedAt($start, $end);
        }

        $appliancesChangedAt = $solarProject->appliances()->max('updated_at');
        $inputsChangedAt = collect([
            $solarProject->updated_at,
            $solarProject->technicalParameter?->updated_at,
            $appliancesChangedAt !== null ? CarbonImmutable::parse($appliancesChangedAt) : null,
        ])->filter()->max();

        return $this->policy->evaluate(
            calculatedAt: $solarProject->calculationResult?->updated_at,
            usedSource: $solarProject->calculationResult?->climate_source,
            hasTechnicalParameters: $solarProject->technicalParameter !== null,
            inputsChangedAt: $inputsChangedAt,
            sourcePriority: $priority,
            sourceLabels: $labels,
            sourceChanges: $changes,
        );
    }
}
