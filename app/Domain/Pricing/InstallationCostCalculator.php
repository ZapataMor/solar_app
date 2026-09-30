<?php

namespace App\Domain\Pricing;

/**
 * Pure pricing rules for installing a system in a given location type.
 */
final class InstallationCostCalculator
{
    /**
     * Logistic surcharge applied when a municipality has no price for the
     * requested location type and the urban base price is used instead.
     */
    public function generalLogisticFactor(string $locationType): float
    {
        return match ($locationType) {
            'rural' => 1.10,
            'rural_dispersa' => 1.20,
            'alta_guajira' => 1.30,
            default => 1.00,
        };
    }

    /**
     * @return array{base_price_per_kw: float, logistic_factor_used: float, final_price_per_kw_used: float, estimated_installation_cost: float}
     */
    public function quote(float $basePricePerKw, float $logisticFactor, float $requiredPowerKw): array
    {
        $finalPrice = $basePricePerKw * $logisticFactor;

        return [
            'base_price_per_kw' => round($basePricePerKw, 2),
            'logistic_factor_used' => round($logisticFactor, 3),
            'final_price_per_kw_used' => round($finalPrice, 2),
            'estimated_installation_cost' => round($requiredPowerKw * $finalPrice, 2),
        ];
    }
}
