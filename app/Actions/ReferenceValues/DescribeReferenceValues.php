<?php

namespace App\Actions\ReferenceValues;

use App\Domain\Property\PropertyType;
use App\Domain\Reference\EnergyTariff;
use App\Domain\Reference\ReferenceValueCatalog;
use App\Domain\Reference\ReferenceValues;
use App\Models\ReferenceValue;
use App\Models\SolarProject;

/**
 * Use case: what the administration screen shows for each reference value (ADR-0015): the value
 * that applies today, the ones scheduled for later, the history and how many projects follow it.
 */
final class DescribeReferenceValues
{
    private const HISTORY_ROWS = 12;

    public function __construct(
        private readonly ReferenceValues $referenceValues,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function __invoke(): array
    {
        $today = now()->toDateString();
        $projectsWithoutOwnTariff = SolarProject::query()->whereNull('energy_rate_cop_kwh')->count();
        $businessRate = EnergyTariff::forPropertyType(
            PropertyType::BUSINESS,
            $this->referenceValues->current(ReferenceValueCatalog::ENERGY_RATE)->value,
            $this->referenceValues->current(ReferenceValueCatalog::COMMERCIAL_CONTRIBUTION)->value,
        );

        return collect(ReferenceValueCatalog::all())
            ->map(function ($definition) use ($today, $projectsWithoutOwnTariff, $businessRate) {
                $rows = ReferenceValue::query()
                    ->with('user:id,name')
                    ->where('key', $definition->key)
                    ->orderByDesc('valid_from')
                    ->get();

                return [
                    'definition' => $definition,
                    'current' => $this->referenceValues->current($definition->key),
                    'scheduled' => $rows->filter(fn (ReferenceValue $row) => $row->valid_from->toDateString() > $today)->sortBy('valid_from')->values(),
                    'history' => $rows->filter(fn (ReferenceValue $row) => $row->valid_from->toDateString() <= $today)->take(self::HISTORY_ROWS)->values(),
                    // Projects whose client did not write a tariff follow the reference one.
                    'projectsUsingIt' => $definition->key === ReferenceValueCatalog::ENERGY_RATE ? $projectsWithoutOwnTariff : null,
                    // What a business pays per kWh today: tariff plus contribution.
                    'businessRate' => $definition->key === ReferenceValueCatalog::ENERGY_RATE ? $businessRate : null,
                ];
            })
            ->values()
            ->all();
    }
}
