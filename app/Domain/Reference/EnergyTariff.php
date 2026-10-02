<?php

namespace App\Domain\Reference;

use App\Domain\Property\PropertyType;

/**
 * The kWh price a kind of place pays with the reference values (ADR-0015).
 *
 * A business pays the contribution on top of the tariff. A house pays the full tariff on the
 * energy above the subsistence consumption, which is the part solar panels replace; an
 * institution (official user) pays the tariff without subsidy or contribution.
 */
final class EnergyTariff
{
    public static function forPropertyType(?string $propertyType, float $baseRateCopKwh, float $commercialContributionPercentage): float
    {
        if (PropertyType::normalize($propertyType) !== PropertyType::BUSINESS) {
            return $baseRateCopKwh;
        }

        return $baseRateCopKwh * (1 + max(0.0, $commercialContributionPercentage) / 100);
    }
}
