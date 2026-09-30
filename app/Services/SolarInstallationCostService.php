<?php

namespace App\Services;

use App\Domain\Pricing\InstallationCostCalculator;
use App\Domain\Pricing\PriceNotAvailable;
use App\Models\Municipality;
use App\Models\MunicipalitySolarPrice;

/**
 * Looks up the municipality price table and delegates the pricing rules to
 * the domain {@see InstallationCostCalculator}.
 */
class SolarInstallationCostService
{
    public function __construct(
        private readonly InstallationCostCalculator $calculator,
    ) {}

    /**
     * @return array<string, float|int|string|null>
     *
     * @throws PriceNotAvailable
     */
    public function calculate(Municipality $municipality, string $locationType, float $requiredPowerKw): array
    {
        $price = $this->resolvePrice($municipality, $locationType);

        if ($price === null) {
            throw new PriceNotAvailable;
        }

        $logisticFactor = $price->location_type === $locationType
            ? (float) $price->logistic_factor
            : $this->calculator->generalLogisticFactor($locationType);

        return [
            ...$this->calculator->quote((float) $price->base_price_per_kw, $logisticFactor, $requiredPowerKw),
            'zone_name' => $price->zone_name,
            'location_type' => $locationType,
            'min_price_per_kw' => $price->min_price_per_kw !== null ? (float) $price->min_price_per_kw : null,
            'max_price_per_kw' => $price->max_price_per_kw !== null ? (float) $price->max_price_per_kw : null,
            'notes' => $price->notes,
        ];
    }

    private function resolvePrice(Municipality $municipality, string $locationType): ?MunicipalitySolarPrice
    {
        $prices = $municipality->solarPrices()
            ->active()
            ->orderByRaw('location_type = ? desc', [$locationType])
            ->orderByRaw("location_type = 'urbana' desc")
            ->get();

        return $prices->firstWhere('location_type', $locationType)
            ?? $prices->firstWhere('location_type', 'urbana');
    }
}
