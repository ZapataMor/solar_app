<?php

namespace App\Domain\Solar;

/**
 * Suggested PV power (kW) to cover a monthly consumption, used to quote the installation
 * before the climate-based calculation runs. It uses a fixed reference of sun hours.
 */
final class RequiredPower
{
    /** Reference peak sun hours per day for La Guajira (kWh/m²/day). */
    public const REFERENCE_DAILY_HSP = 5.8;

    public static function suggestedKw(float $monthlyConsumptionKwh, float $systemLossesPercentage): ?float
    {
        if ($monthlyConsumptionKwh <= 0 || $systemLossesPercentage < 0 || $systemLossesPercentage >= 100) {
            return null;
        }

        $performanceRatio = 1 - ($systemLossesPercentage / 100);

        return round($monthlyConsumptionKwh / (self::REFERENCE_DAILY_HSP * 30 * $performanceRatio), 2);
    }
}
