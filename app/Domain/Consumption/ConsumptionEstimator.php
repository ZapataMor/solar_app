<?php

namespace App\Domain\Consumption;

/**
 * Turns the appliances a client wants to power into monthly energy consumption (kWh).
 */
final class ConsumptionEstimator
{
    public function __construct(
        private readonly ApplianceCatalog $catalog = new ApplianceCatalog,
    ) {}

    public function monthlyKwh(ApplianceLoad $load): float
    {
        $hoursPerDay = $this->catalog->isAlwaysOn($load->key) ? 24.0 : max(0.0, min(24.0, $load->hoursPerDay));

        return $this->catalog->watts($load->key, $load->variant)
            * max(0, $load->quantity)
            * $hoursPerDay
            * ApplianceCatalog::DAYS_PER_MONTH
            / 1000;
    }

    /**
     * @param  iterable<ApplianceLoad>  $loads
     */
    public function totalMonthlyKwh(iterable $loads): float
    {
        $total = 0.0;

        foreach ($loads as $load) {
            $total += $this->monthlyKwh($load);
        }

        return $total;
    }
}
