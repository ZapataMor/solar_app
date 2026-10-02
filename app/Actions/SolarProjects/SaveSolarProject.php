<?php

namespace App\Actions\SolarProjects;

use App\Domain\Pricing\PriceNotAvailable;
use App\Models\Municipality;
use App\Models\SolarProject;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Use case: create or update a project from the guided form (ADR-0013): kind of property,
 * location, roof, tariff and name, with the installation quoted for its municipality.
 *
 * The consumption is not part of this form: it comes from the appliances of the consumption
 * diary (SaveProjectAppliance), so a new project starts at 0 kWh and an edit keeps it.
 */
final class SaveSolarProject
{
    public function __construct(
        private readonly QuoteInstallation $quoteInstallation,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Validated SolarProjectRequest data.
     *
     * @throws PriceNotAvailable
     */
    public function __invoke(User $owner, array $data, ?SolarProject $solarProject = null): SolarProject
    {
        $municipality = Municipality::query()->findOrFail($data['municipality_id']);
        $quote = ($this->quoteInstallation)(
            $municipality,
            (string) $data['location_type'],
            $solarProject?->monthlyConsumption() ?? 0.0,
            (float) $data['system_losses_percentage'],
        );

        return DB::transaction(function () use ($owner, $data, $solarProject, $municipality, $quote): SolarProject {
            $attributes = [
                ...$this->projectAttributes($data),
                ...$this->locationAttributes($data, $municipality),
                ...$quote,
            ];

            if ($solarProject === null) {
                $solarProject = $owner->solarProjects()->create([
                    ...$attributes,
                    // Only when creating: it defines the diary spaces, so it never changes afterwards.
                    'property_type' => $data['property_type'],
                    'monthly_consumption_kwh' => 0,
                ]);
            } else {
                $solarProject->update($attributes);
            }

            $solarProject->technicalParameter()->updateOrCreate(
                ['solar_project_id' => $solarProject->id],
                $this->technicalParameterAttributes($data),
            );

            return $solarProject;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function projectAttributes(array $data): array
    {
        $attributes = [
            'name' => $data['name'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            // Null follows the reference tariff (ADR-0015); a number is the client's own.
            'energy_rate_cop_kwh' => $data['energy_rate_cop_kwh'] ?? null,
        ];

        // The notes live in their own tab now: only touch them when they were sent.
        if (array_key_exists('description', $data)) {
            $attributes['description'] = $data['description'];
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function locationAttributes(array $data, Municipality $municipality): array
    {
        return [
            'location_name' => "{$municipality->name}, La Guajira, Colombia",
            'municipality_id' => $municipality->id,
            'latitude' => $data['latitude'] ?? $municipality->latitude ?? SolarProject::LATITUDE,
            'longitude' => $data['longitude'] ?? $municipality->longitude ?? SolarProject::LONGITUDE,
            'location_type' => $data['location_type'],
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
