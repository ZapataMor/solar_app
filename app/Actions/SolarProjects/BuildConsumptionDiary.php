<?php

namespace App\Actions\SolarProjects;

use App\Domain\Consumption\ApplianceCatalog;
use App\Domain\Consumption\ApplianceLoad;
use App\Domain\Consumption\ConsumptionEstimator;
use App\Domain\Property\PropertyType;
use App\Models\SolarProject;
use App\Models\SolarProjectAppliance;

/**
 * Use case: the consumption diary of a project (ADR-0013): its appliances grouped by the spaces
 * of its kind of property, with the kWh of each one, of each space and in total.
 */
final class BuildConsumptionDiary
{
    public function __construct(
        private readonly ApplianceCatalog $catalog,
        private readonly ConsumptionEstimator $consumptionEstimator,
    ) {}

    /**
     * @return array{
     *     spaces: list<array{key: string, label: string, kwh: float, share: float, items: list<array<string, mixed>>}>,
     *     totalKwh: float,
     *     dailyKwh: float,
     *     applianceCount: int,
     *     biggest: array{label: string, kwh: float, share: float}|null
     * }
     */
    public function __invoke(SolarProject $solarProject): array
    {
        $type = $solarProject->property_type;
        $spaces = [];

        foreach (PropertyType::spaces($type) as $key => $label) {
            $spaces[$key] = ['key' => $key, 'label' => $label, 'kwh' => 0.0, 'share' => 0.0, 'items' => []];
        }

        $biggest = null;
        $appliances = $solarProject->appliances()->orderBy('id')->get()
            ->filter(fn (SolarProjectAppliance $appliance) => $this->catalog->hasVariant($appliance->appliance_key, $appliance->variant_key));

        foreach ($appliances as $appliance) {
            $item = $this->item($appliance);
            $space = PropertyType::spaceOf($type, $appliance->space);

            $spaces[$space]['items'][] = $item;
            $spaces[$space]['kwh'] += $item['kwh'];

            if ($biggest === null || $item['kwh'] > $biggest['kwh']) {
                $biggest = ['label' => $item['label'], 'kwh' => $item['kwh'], 'share' => 0.0];
            }
        }

        $total = array_sum(array_column($spaces, 'kwh'));

        foreach ($spaces as $key => $space) {
            $spaces[$key]['share'] = $total > 0 ? $space['kwh'] / $total * 100 : 0.0;
        }

        if ($biggest !== null) {
            $biggest['share'] = $total > 0 ? $biggest['kwh'] / $total * 100 : 0.0;
        }

        return [
            'spaces' => array_values($spaces),
            'totalKwh' => $total,
            'dailyKwh' => $total / ApplianceCatalog::DAYS_PER_MONTH,
            'applianceCount' => $appliances->count(),
            'biggest' => $biggest,
        ];
    }

    /**
     * @return array<string, mixed> One diary row; also what the edit sheet is opened with.
     */
    private function item(SolarProjectAppliance $appliance): array
    {
        $key = $appliance->appliance_key;
        $variant = $appliance->variant_key;
        $hoursPerDay = (float) $appliance->hours_per_day;

        return [
            'id' => $appliance->id,
            'space' => $appliance->space,
            'key' => $key,
            'variant' => $variant,
            'label' => $this->catalog->label($key),
            'variantLabel' => $this->catalog->variantLabel($key, $variant),
            'icon' => $this->catalog->icon($key, $variant),
            'quantity' => (int) $appliance->quantity,
            'hoursPerDay' => $hoursPerDay,
            'usageText' => $this->usageText($key, $hoursPerDay),
            'kwh' => $this->consumptionEstimator->monthlyKwh(new ApplianceLoad($key, $variant, (int) $appliance->quantity, $hoursPerDay)),
        ];
    }

    private function usageText(string $key, float $hoursPerDay): string
    {
        return match ($this->catalog->usage($key)) {
            'always' => 'todo el día',
            'week' => $this->hours($hoursPerDay * 7).' a la semana',
            default => $this->hours($hoursPerDay).' al día',
        };
    }

    private function hours(float $hours): string
    {
        $rounded = round($hours, 1);
        $text = fmod($rounded, 1.0) === 0.0 ? number_format($rounded, 0) : number_format($rounded, 1, ',', '.');

        return $text.' '.($rounded === 1.0 ? 'hora' : 'horas');
    }
}
