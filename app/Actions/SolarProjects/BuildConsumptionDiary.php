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
     * Every kWh figure comes with its monthly cost in pesos (`cost`), at the project's tariff, for
     * clients who understand money better than kWh. It is what that energy costs on the bill today.
     *
     * @return array{
     *     spaces: list<array{key: string, label: string, kwh: float, cost: float, share: float, items: list<array<string, mixed>>}>,
     *     totalKwh: float,
     *     totalCost: float,
     *     dailyKwh: float,
     *     ratePerKwh: float,
     *     applianceCount: int,
     *     biggest: array{label: string, kwh: float, cost: float, share: float}|null
     * }
     */
    public function __invoke(SolarProject $solarProject): array
    {
        $type = $solarProject->property_type;
        $rate = max(0.0, (float) $solarProject->energy_rate_cop_kwh);
        $spaces = [];

        foreach (PropertyType::spaces($type) as $key => $label) {
            $spaces[$key] = ['key' => $key, 'label' => $label, 'kwh' => 0.0, 'cost' => 0.0, 'share' => 0.0, 'items' => []];
        }

        $biggest = null;
        $appliances = $solarProject->appliances()->orderBy('id')->get()
            ->filter(fn (SolarProjectAppliance $appliance) => $this->catalog->hasVariant($appliance->appliance_key, $appliance->variant_key));

        foreach ($appliances as $appliance) {
            $item = $this->item($appliance, $rate);
            $space = PropertyType::spaceOf($type, $appliance->space);

            $spaces[$space]['items'][] = $item;
            $spaces[$space]['kwh'] += $item['kwh'];
            $spaces[$space]['cost'] += $item['cost'];

            if ($biggest === null || $item['kwh'] > $biggest['kwh']) {
                $biggest = ['label' => $item['label'], 'kwh' => $item['kwh'], 'cost' => $item['cost'], 'share' => 0.0];
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
            'totalCost' => $total * $rate,
            'dailyKwh' => $total / ApplianceCatalog::DAYS_PER_MONTH,
            'ratePerKwh' => $rate,
            'applianceCount' => $appliances->count(),
            'biggest' => $biggest,
        ];
    }

    /**
     * @return array<string, mixed> One diary row; also what the edit sheet is opened with.
     */
    private function item(SolarProjectAppliance $appliance, float $rate): array
    {
        $key = $appliance->appliance_key;
        $variant = $appliance->variant_key;
        $hoursPerDay = (float) $appliance->hours_per_day;
        $kwh = $this->consumptionEstimator->monthlyKwh(new ApplianceLoad($key, $variant, (int) $appliance->quantity, $hoursPerDay));

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
            'kwh' => $kwh,
            'cost' => $kwh * $rate,
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
