<?php

namespace App\Actions\SolarProjects;

use App\Domain\Consumption\ApplianceCatalog;
use App\Domain\Consumption\ApplianceLoad;
use App\Domain\Consumption\ConsumptionEstimator;
use App\Domain\Pricing\PriceNotAvailable;
use App\Models\SolarProject;
use App\Models\SolarProjectAppliance;

/**
 * Use case: the appliances are the base of the calculation (ADR-0013). After any change in the
 * consumption diary, the project's consumption, suggested power and installation quote follow
 * them, and the project is stamped as changed so its calculation shows the "!" (ADR-0010).
 *
 * A project that gives its consumption from the bill (ADR-0020) keeps the kWh of the bill: its
 * appliances, if it still has any from before, do not count.
 */
final class SyncProjectConsumption
{
    public function __construct(
        private readonly ApplianceCatalog $catalog,
        private readonly ConsumptionEstimator $consumptionEstimator,
        private readonly QuoteInstallation $quoteInstallation,
    ) {}

    public function __invoke(SolarProject $solarProject): void
    {
        if ($solarProject->usesBillConsumption()) {
            return;
        }

        $monthlyKwh = round($this->consumptionEstimator->totalMonthlyKwh(
            $solarProject->appliances()->get()
                ->filter(fn (SolarProjectAppliance $appliance) => $this->catalog->hasVariant($appliance->appliance_key, $appliance->variant_key))
                ->map(fn (SolarProjectAppliance $appliance) => new ApplianceLoad(
                    $appliance->appliance_key,
                    $appliance->variant_key,
                    (int) $appliance->quantity,
                    (float) $appliance->hours_per_day,
                ))
        ), 2);

        // All three scales at once: with 0 the model would otherwise restore them from the old annual value.
        $solarProject->fill([
            'monthly_consumption_kwh' => $monthlyKwh,
            'daily_consumption_kwh' => $monthlyKwh / ApplianceCatalog::DAYS_PER_MONTH,
            'annual_consumption_kwh' => $monthlyKwh * 12,
            ...$this->quote($solarProject, $monthlyKwh),
        ]);

        if ($solarProject->isDirty()) {
            $solarProject->save();
        } else {
            $solarProject->touch();
        }
    }

    /**
     * @return array<string, float|null>
     */
    private function quote(SolarProject $solarProject, float $monthlyKwh): array
    {
        $solarProject->loadMissing(['municipality', 'technicalParameter']);

        if ($solarProject->municipality === null || $solarProject->technicalParameter === null || $solarProject->location_type === null) {
            return [];
        }

        try {
            return ($this->quoteInstallation)(
                $solarProject->municipality,
                (string) $solarProject->location_type,
                $monthlyKwh,
                (float) $solarProject->technicalParameter->system_losses_percentage,
            );
        } catch (PriceNotAvailable) {
            return [];
        }
    }
}
