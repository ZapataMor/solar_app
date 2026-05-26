<?php

namespace App\Services;

use App\Models\Municipality;
use App\Models\MunicipalitySolarPrice;
use RuntimeException;

class SolarInstallationCostService
{
    /**
     * @return array<string, float|int|string|null>
     */
    public function calculate(Municipality $municipality, string $locationType, float $requiredPowerKw): array
    {
        $price = $this->resolvePrice($municipality, $locationType);

        if ($price === null) {
            throw new RuntimeException('No hay precio disponible para esa ubicacion.');
        }

        $basePrice = (float) $price->base_price_per_kw;
        $logisticFactor = $price->location_type === $locationType
            ? (float) $price->logistic_factor
            : $this->generalLogisticFactor($locationType);
        $finalPrice = $basePrice * $logisticFactor;

        return [
            'base_price_per_kw' => round($basePrice, 2),
            'logistic_factor_used' => round($logisticFactor, 3),
            'final_price_per_kw_used' => round($finalPrice, 2),
            'estimated_installation_cost' => round($requiredPowerKw * $finalPrice, 2),
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

    private function generalLogisticFactor(string $locationType): float
    {
        return match ($locationType) {
            'rural' => 1.10,
            'rural_dispersa' => 1.20,
            'alta_guajira' => 1.30,
            default => 1.00,
        };
    }
}
