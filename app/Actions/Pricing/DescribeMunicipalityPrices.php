<?php

namespace App\Actions\Pricing;

use App\Models\Municipality;
use App\Models\MunicipalitySolarPrice;
use App\Models\SolarProject;

/**
 * Use case: the price per kW of every municipality, as an administrator keeps it (ADR-0024).
 *
 * Every active municipality is listed, even the ones with no price: a gap is the useful thing to
 * see, because a project there cannot be quoted at all (`PriceNotAvailable`).
 */
final class DescribeMunicipalityPrices
{
    public const LOCATION_TYPES = [
        'urbana' => 'Urbana',
        'rural' => 'Rural',
        'rural_dispersa' => 'Rural dispersa',
        'alta_guajira' => 'Alta Guajira',
    ];

    /**
     * @return array{municipalities: list<array<string, mixed>>, locationTypes: array<string, string>, missing: int}
     */
    public function __invoke(): array
    {
        $prices = MunicipalitySolarPrice::query()->get()->groupBy('municipality_id');
        // How many projects were quoted in each municipality: changing a price never touches them
        // (ADR-0024), and knowing how many there are says how much the new price will not explain.
        $projects = SolarProject::query()
            ->selectRaw('municipality_id, count(*) as projects')
            ->whereNotNull('municipality_id')
            ->groupBy('municipality_id')
            ->pluck('projects', 'municipality_id');

        $municipalities = Municipality::query()->active()->orderBy('name')->get();

        return [
            'municipalities' => $municipalities->map(fn (Municipality $municipality): array => [
                'id' => $municipality->id,
                'name' => $municipality->name,
                'zone' => $municipality->zone,
                'projects' => (int) ($projects[$municipality->id] ?? 0),
                'prices' => ($prices[$municipality->id] ?? collect())
                    ->keyBy('location_type')
                    ->map(fn (MunicipalitySolarPrice $price): array => [
                        'id' => $price->id,
                        'basePricePerKw' => (float) $price->base_price_per_kw,
                        'logisticFactor' => (float) $price->logistic_factor,
                        'finalPricePerKw' => (float) $price->base_price_per_kw * (float) $price->logistic_factor,
                        'notes' => $price->notes,
                        'active' => (bool) $price->active,
                    ])->all(),
            ])->all(),
            'locationTypes' => self::LOCATION_TYPES,
            'missing' => $municipalities
                ->filter(fn (Municipality $municipality) => ! isset($prices[$municipality->id]))
                ->count(),
        ];
    }
}
