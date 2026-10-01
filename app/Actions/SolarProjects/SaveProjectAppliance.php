<?php

namespace App\Actions\SolarProjects;

use App\Domain\Consumption\ApplianceCatalog;
use App\Models\SolarProject;
use App\Models\SolarProjectAppliance;
use Illuminate\Support\Facades\DB;

/**
 * Use case: add an appliance to a space of the consumption diary, or change one (ADR-0013).
 */
final class SaveProjectAppliance
{
    public function __construct(
        private readonly ApplianceCatalog $catalog,
        private readonly SyncProjectConsumption $syncProjectConsumption,
    ) {}

    /**
     * @param  array{space: string, key: string, variant: string, quantity: int|string, hours_per_day: float|string}  $data  Validated ProjectApplianceRequest data.
     */
    public function __invoke(SolarProject $solarProject, array $data, ?SolarProjectAppliance $appliance = null): SolarProjectAppliance
    {
        $attributes = [
            'space' => $data['space'],
            'appliance_key' => $data['key'],
            'variant_key' => $data['variant'],
            'quantity' => (int) $data['quantity'],
            // Always-on appliances (fridges, modems…) run 24 h whatever the browser sent.
            'hours_per_day' => $this->catalog->isAlwaysOn($data['key'])
                ? 24
                : round(max(0, min(24, (float) $data['hours_per_day'])), 2),
        ];

        return DB::transaction(function () use ($solarProject, $attributes, $appliance): SolarProjectAppliance {
            if ($appliance === null) {
                $appliance = $solarProject->appliances()->create($attributes);
            } else {
                $appliance->update($attributes);
            }

            ($this->syncProjectConsumption)($solarProject);

            return $appliance;
        });
    }
}
