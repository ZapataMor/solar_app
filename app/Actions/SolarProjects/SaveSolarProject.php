<?php

namespace App\Actions\SolarProjects;

use App\Domain\Consumption\ConsumptionMode;
use App\Domain\Pricing\PriceNotAvailable;
use App\Models\Municipality;
use App\Models\SolarProject;
use App\Models\User;
use App\Services\SolarInstallationCostService;
use Illuminate\Support\Facades\DB;

/**
 * Use case: create or update a project with its technical parameters and
 * the installation cost quoted for its municipality.
 */
final class SaveSolarProject
{
    public function __construct(
        private readonly SolarInstallationCostService $installationCost,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Validated SolarProjectRequest data.
     *
     * @throws PriceNotAvailable
     */
    public function __invoke(User $owner, array $data, ?SolarProject $solarProject = null): SolarProject
    {
        $municipality = Municipality::query()->findOrFail($data['municipality_id']);
        $cost = $this->installationCost->calculate(
            $municipality,
            (string) $data['location_type'],
            (float) $data['required_power_kw'],
        );

        return DB::transaction(function () use ($owner, $data, $solarProject, $municipality, $cost): SolarProject {
            $attributes = [
                ...$this->projectAttributes($data),
                ...$this->locationAttributes($data, $municipality, $cost),
            ];

            if ($solarProject === null) {
                $solarProject = $owner->solarProjects()->create($attributes);
            } else {
                $solarProject->update($attributes);
            }

            $solarProject->technicalParameter()->updateOrCreate(
                ['solar_project_id' => $solarProject->id],
                $this->technicalParameterAttributes($data),
            );

            $this->syncAppliances($solarProject, $data);

            return $solarProject;
        });
    }

    /**
     * Appliance mode replaces the stored appliances; bill mode clears them. Without a mode
     * (e.g. older clients) the appliances are left untouched.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncAppliances(SolarProject $solarProject, array $data): void
    {
        $mode = $data['consumption_mode'] ?? null;

        if ($mode === null) {
            return;
        }

        $wanted = $mode === ConsumptionMode::APPLIANCES
            ? array_map(fn (array $row) => [
                'appliance_key' => (string) $row['key'],
                'variant_key' => (string) $row['variant'],
                'quantity' => (int) $row['quantity'],
                'hours_per_day' => round((float) $row['hours_per_day'], 2),
            ], array_values($data['appliances'] ?? []))
            : [];

        // Untouched list: keep the rows, so saving without changes does not mark the calculation stale.
        $current = $solarProject->appliances()->orderBy('id')->get()
            ->map(fn ($appliance) => [
                'appliance_key' => $appliance->appliance_key,
                'variant_key' => $appliance->variant_key,
                'quantity' => (int) $appliance->quantity,
                'hours_per_day' => round((float) $appliance->hours_per_day, 2),
            ])
            ->all();

        if ($current === $wanted) {
            return;
        }

        $solarProject->appliances()->delete();
        $solarProject->appliances()->createMany($wanted);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function projectAttributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'monthly_consumption_kwh' => $data['monthly_consumption_kwh'],
            'energy_rate_cop_kwh' => $data['energy_rate_cop_kwh'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $cost
     * @return array<string, mixed>
     */
    private function locationAttributes(array $data, Municipality $municipality, array $cost): array
    {
        return [
            'location_name' => "{$municipality->name}, La Guajira, Colombia",
            'municipality_id' => $municipality->id,
            'latitude' => $data['latitude'] ?? $municipality->latitude ?? SolarProject::LATITUDE,
            'longitude' => $data['longitude'] ?? $municipality->longitude ?? SolarProject::LONGITUDE,
            'location_type' => $data['location_type'],
            'required_power_kw' => $data['required_power_kw'],
            'base_price_per_kw' => $cost['base_price_per_kw'],
            'logistic_factor_used' => $cost['logistic_factor_used'],
            'final_price_per_kw_used' => $cost['final_price_per_kw_used'],
            'estimated_installation_cost' => $cost['estimated_installation_cost'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function technicalParameterAttributes(array $data): array
    {
        $systemLossesPercentage = (float) $data['system_losses_percentage'];

        return [
            'available_area_m2' => $data['available_area_m2'],
            'usable_area_percentage' => $data['usable_area_percentage'],
            'panel_power_w' => $data['panel_power_w'],
            'panel_area_m2' => $data['panel_area_m2'],
            'performance_ratio' => 1 - ($systemLossesPercentage / 100),
            'system_losses_percentage' => $systemLossesPercentage,
        ];
    }
}
