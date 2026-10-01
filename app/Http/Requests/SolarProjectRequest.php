<?php

namespace App\Http\Requests;

use App\Domain\Consumption\ApplianceCatalog;
use App\Domain\Consumption\ApplianceLoad;
use App\Domain\Consumption\ConsumptionEstimator;
use App\Domain\Consumption\ConsumptionMode;
use App\Models\MunicipalitySolarPrice;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SolarProjectRequest extends FormRequest
{
    private const REFERENCE_DAILY_HSP = 5.8;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // In appliance mode the consumption is computed here from the catalog, never trusted from the browser.
        if ($this->input('consumption_mode') === ConsumptionMode::APPLIANCES) {
            $this->merge([
                'monthly_consumption_kwh' => round((new ConsumptionEstimator)->totalMonthlyKwh($this->validApplianceLoads()), 2),
            ]);
        }

        if (! $this->filled('end_date') && $this->filled('start_date')) {
            $this->merge([
                'end_date' => $this->input('start_date'),
            ]);
        }

        if (! $this->filled('required_power_kw')) {
            $suggestedPowerKw = $this->suggestedRequiredPowerKw();

            if ($suggestedPowerKw !== null) {
                $this->merge([
                    'required_power_kw' => $suggestedPowerKw,
                ]);
            }
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'monthly_consumption_kwh' => ['required', 'numeric', 'gt:0'],
            'energy_rate_cop_kwh' => ['required', 'numeric', 'gte:0'],
            'available_area_m2' => ['required', 'numeric', 'gt:0'],
            'usable_area_percentage' => ['required', 'numeric', 'between:1,100'],
            'panel_power_w' => ['required', 'numeric', 'gt:0'],
            'panel_area_m2' => ['required', 'numeric', 'gt:0'],
            'system_losses_percentage' => ['required', 'numeric', 'between:0,100'],
            'municipality_id' => ['required', 'integer', Rule::exists('municipalities', 'id')->where('active', true)],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'location_type' => ['required', 'string', Rule::in(MunicipalitySolarPrice::LOCATION_TYPES)],
            'required_power_kw' => ['required', 'numeric', 'gt:0'],
            'consumption_mode' => ['nullable', Rule::in(ConsumptionMode::ALL)],
            'appliances' => ['exclude_unless:consumption_mode,'.ConsumptionMode::APPLIANCES, 'required', 'array', 'min:1', 'max:60'],
            'appliances.*.key' => ['exclude_unless:consumption_mode,'.ConsumptionMode::APPLIANCES, 'required', 'string', Rule::in(array_keys((new ApplianceCatalog)->all()))],
            'appliances.*.variant' => ['exclude_unless:consumption_mode,'.ConsumptionMode::APPLIANCES, 'required', 'string', $this->variantRule()],
            'appliances.*.quantity' => ['exclude_unless:consumption_mode,'.ConsumptionMode::APPLIANCES, 'required', 'integer', 'between:1,100'],
            'appliances.*.hours_per_day' => ['exclude_unless:consumption_mode,'.ConsumptionMode::APPLIANCES, 'required', 'numeric', 'between:0,24'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'appliances.required' => 'Agrega al menos un equipo en la etapa de consumo.',
            'appliances.min' => 'Agrega al menos un equipo en la etapa de consumo.',
            'monthly_consumption_kwh.gt' => 'El consumo mensual debe ser mayor que 0: revisa tus equipos y sus horas de uso.',
        ];
    }

    /**
     * The chosen variant must exist for the appliance on the same row.
     */
    private function variantRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $index = explode('.', $attribute)[1] ?? null;
            $key = (string) $this->input("appliances.{$index}.key");

            if (! (new ApplianceCatalog)->hasVariant($key, (string) $value)) {
                $fail('Uno de los equipos tiene una opción que no existe.');
            }
        };
    }

    /**
     * Rows with a known appliance and variant, as domain loads (invalid rows are reported by the rules).
     *
     * @return list<ApplianceLoad>
     */
    private function validApplianceLoads(): array
    {
        $catalog = new ApplianceCatalog;
        $rows = $this->input('appliances');

        if (! is_array($rows)) {
            return [];
        }

        return collect($rows)
            ->filter(fn ($row) => is_array($row)
                && $catalog->hasVariant((string) ($row['key'] ?? ''), (string) ($row['variant'] ?? '')))
            ->map(fn (array $row) => new ApplianceLoad(
                key: (string) $row['key'],
                variant: (string) $row['variant'],
                quantity: max(0, (int) ($row['quantity'] ?? 0)),
                hoursPerDay: (float) str_replace(',', '.', (string) ($row['hours_per_day'] ?? 0)),
            ))
            ->values()
            ->all();
    }

    private function suggestedRequiredPowerKw(): ?float
    {
        $monthlyConsumption = $this->numberInput('monthly_consumption_kwh');
        $systemLosses = $this->numberInput('system_losses_percentage');

        if ($monthlyConsumption === null || $monthlyConsumption <= 0 || $systemLosses === null || $systemLosses < 0 || $systemLosses >= 100) {
            return null;
        }

        $performanceRatio = 1 - ($systemLosses / 100);
        $monthlyGenerationPerKw = self::REFERENCE_DAILY_HSP * 30 * $performanceRatio;

        if ($monthlyGenerationPerKw <= 0) {
            return null;
        }

        return round($monthlyConsumption / $monthlyGenerationPerKw, 2);
    }

    private function numberInput(string $key): ?float
    {
        $value = $this->input($key);

        if ($value === null || $value === '') {
            return null;
        }

        $normalized = str_replace(',', '.', (string) $value);

        return is_numeric($normalized) ? (float) $normalized : null;
    }
}
