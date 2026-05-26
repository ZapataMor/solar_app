<?php

namespace App\Http\Requests;

use App\Models\MunicipalitySolarPrice;
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
        ];
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
