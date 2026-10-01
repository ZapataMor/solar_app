<?php

namespace App\Actions\SolarProjects;

use App\Domain\Pricing\PriceNotAvailable;
use App\Domain\Solar\RequiredPower;
use App\Models\Municipality;
use App\Services\SolarInstallationCostService;

/**
 * Use case: the suggested power for a consumption and its installation quote in a municipality.
 * Without consumption (a new project before its appliances) only the price per kW is known.
 */
final class QuoteInstallation
{
    public function __construct(
        private readonly SolarInstallationCostService $installationCost,
    ) {}

    /**
     * @return array{required_power_kw: float|null, base_price_per_kw: float, logistic_factor_used: float, final_price_per_kw_used: float, estimated_installation_cost: float|null}
     *
     * @throws PriceNotAvailable
     */
    public function __invoke(
        Municipality $municipality,
        string $locationType,
        float $monthlyConsumptionKwh,
        float $systemLossesPercentage,
    ): array {
        $requiredPowerKw = RequiredPower::suggestedKw($monthlyConsumptionKwh, $systemLossesPercentage);
        $cost = $this->installationCost->calculate($municipality, $locationType, $requiredPowerKw ?? 0.0);

        return [
            'required_power_kw' => $requiredPowerKw,
            'base_price_per_kw' => $cost['base_price_per_kw'],
            'logistic_factor_used' => $cost['logistic_factor_used'],
            'final_price_per_kw_used' => $cost['final_price_per_kw_used'],
            'estimated_installation_cost' => $requiredPowerKw === null ? null : $cost['estimated_installation_cost'],
        ];
    }
}
