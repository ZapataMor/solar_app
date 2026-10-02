<?php

namespace App\Actions\Catalog;

use App\Domain\Consumption\ApplianceCatalog;
use App\Domain\Reference\ReferenceValueCatalog;
use App\Domain\Reference\ReferenceValues;
use App\Models\CatalogAppliance;
use App\Models\SolarProjectAppliance;

/**
 * Use case: the appliance catalog as a reference table for administrators (ADR-0017): how much each
 * one draws and consumes, sorted and filtered.
 *
 * "Consumo típico" is one unit of the option the diary proposes, used its default hours: what a
 * client would get by picking it without changing anything. The power range and the average of the
 * options are shown too, because the options of one appliance can differ a lot (a 9.000 BTU inverter
 * air conditioner draws a quarter of a 24.000 BTU conventional one).
 */
final class DescribeApplianceCatalog
{
    public const SORTS = [
        'consumo' => 'Consumo típico',
        'potencia' => 'Potencia máxima',
        'promedio' => 'Potencia promedio',
        'nombre' => 'Nombre',
        'variantes' => 'Número de opciones',
    ];

    public const SEGMENTS = [
        'todos' => 'Todos',
        ApplianceCatalog::SEGMENT_HOME => 'Hogar',
        ApplianceCatalog::SEGMENT_BUSINESS => 'Negocio',
    ];

    public function __construct(
        private readonly ApplianceCatalog $catalog,
        private readonly ReferenceValues $referenceValues,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function __invoke(string $sort = 'consumo', string $segment = 'todos'): array
    {
        $rate = $this->referenceValues->current(ReferenceValueCatalog::ENERGY_RATE)->value;
        $projectsUsing = SolarProjectAppliance::query()
            ->selectRaw('appliance_key, count(distinct solar_project_id) as projects')
            ->groupBy('appliance_key')
            ->pluck('projects', 'appliance_key');
        $customIds = CatalogAppliance::query()->pluck('id', 'key');

        $rows = collect($this->catalog->all())
            ->filter(fn (array $appliance) => $segment === 'todos' || in_array($segment, $appliance['segments'] ?? [], true))
            ->map(function (array $appliance, string $key) use ($rate, $projectsUsing, $customIds) {
                $watts = $this->catalog->variantWatts($key);
                $typicalKwh = $this->catalog->typicalMonthlyKwh($key);

                return [
                    'key' => $key,
                    'id' => $customIds[$key] ?? null,
                    'label' => $appliance['label'],
                    'icon' => $appliance['icon'] ?? 'plug',
                    'hint' => $appliance['hint'] ?? null,
                    'builtIn' => $this->catalog->isBuiltIn($key),
                    'active' => ($appliance['active'] ?? true) !== false,
                    'segments' => $this->segmentsText($appliance['segments'] ?? []),
                    'usage' => $this->usageText($appliance),
                    'variants' => collect($watts)->map(fn (float $variantWatts, string $variant) => [
                        'label' => $this->catalog->variantLabel($key, $variant) ?: 'Única',
                        'watts' => $variantWatts,
                        'monthlyKwh' => $this->catalog->typicalMonthlyKwh($key, $variant),
                        'isDefault' => $variant === ($appliance['default_variant'] ?? 'default'),
                    ])->values()->all(),
                    'minWatts' => min($watts),
                    'maxWatts' => max($watts),
                    'averageWatts' => array_sum($watts) / count($watts),
                    'typicalKwh' => $typicalKwh,
                    'typicalCop' => $typicalKwh * $rate,
                    'projects' => (int) ($projectsUsing[$key] ?? 0),
                ];
            });

        $sorted = match ($sort) {
            'potencia' => $rows->sortByDesc('maxWatts'),
            'promedio' => $rows->sortByDesc('averageWatts'),
            'nombre' => $rows->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE),
            'variantes' => $rows->sortByDesc(fn (array $row) => count($row['variants'])),
            default => $rows->sortByDesc('typicalKwh'),
        };

        return $sorted->values()->all();
    }

    /**
     * @param  list<string>  $segments
     */
    private function segmentsText(array $segments): string
    {
        $home = in_array(ApplianceCatalog::SEGMENT_HOME, $segments, true);
        $business = in_array(ApplianceCatalog::SEGMENT_BUSINESS, $segments, true);

        return match (true) {
            $home && $business => 'Hogar y negocio',
            $business => 'Negocio',
            default => 'Hogar',
        };
    }

    /**
     * @param  array<string, mixed>  $appliance
     */
    private function usageText(array $appliance): string
    {
        $hours = (float) ($appliance['default_hours'] ?? 0);
        $number = rtrim(rtrim(number_format($hours, 1, ',', '.'), '0'), ',');

        return match ($appliance['usage'] ?? 'day') {
            'always' => 'Todo el día',
            'week' => "{$number} h a la semana",
            default => "{$number} h al día",
        };
    }
}
