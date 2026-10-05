<?php

namespace App\Actions\Installers;

use App\Domain\Reference\ReferenceValueCatalog;
use App\Domain\Reference\ReferenceValues;
use App\Models\Installer;
use App\Models\MunicipalitySolarPrice;

/**
 * Use case: the prices the app quotes with, for the installer who receives those quotes (ADR-0023).
 *
 * It is where the "presupuesto de referencia" of every request comes from: the price per kW of the
 * municipality times the power the consumption asks for. Showing it means the installer knows what
 * number the client already has in their head before reading his own.
 *
 * Read-only: these are the platform's numbers, kept by an administrator (ADR-0015).
 */
final class DescribeReferencePrices
{
    private const LOCATION_TYPES = [
        'urbana' => 'Urbana',
        'rural' => 'Rural',
        'rural_dispersa' => 'Rural dispersa',
        'alta_guajira' => 'Alta Guajira',
    ];

    public function __construct(
        private readonly ReferenceValues $referenceValues,
    ) {}

    /**
     * @return array{municipalities: list<array<string, mixed>>, locationTypes: array<string, string>, covered: list<string>, energyRate: float, commercialRate: float}
     */
    public function __invoke(?Installer $installer = null): array
    {
        $prices = MunicipalitySolarPrice::query()
            ->where('active', true)
            ->with('municipality:id,name,zone')
            ->get()
            ->filter(fn (MunicipalitySolarPrice $price) => $price->municipality !== null)
            ->groupBy(fn (MunicipalitySolarPrice $price) => $price->municipality->name)
            ->sortKeys();

        $energyRate = $this->referenceValues->current(ReferenceValueCatalog::ENERGY_RATE)->value;
        $contribution = $this->referenceValues->current(ReferenceValueCatalog::COMMERCIAL_CONTRIBUTION)->value;

        return [
            'municipalities' => $prices->map(fn ($rows, string $name): array => [
                'name' => $name,
                'zone' => $rows->first()->municipality->zone,
                // The final price is the base one times the logistic factor of getting there.
                'prices' => $rows->mapWithKeys(fn (MunicipalitySolarPrice $price) => [
                    $price->location_type => (float) $price->base_price_per_kw * (float) $price->logistic_factor,
                ])->all(),
            ])->values()->all(),
            // Only the kinds of location that someone priced: an empty column reads as missing data.
            'locationTypes' => array_intersect_key(self::LOCATION_TYPES, array_flip(
                $prices->flatten()->pluck('location_type')->unique()->all(),
            )),
            // What this installer covers is worth reading first; the rest is context.
            'covered' => $installer?->municipalities->pluck('name')->all() ?? [],
            'energyRate' => $energyRate,
            'commercialRate' => $energyRate * (1 + $contribution / 100),
        ];
    }
}
