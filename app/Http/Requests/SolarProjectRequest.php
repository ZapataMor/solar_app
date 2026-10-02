<?php

namespace App\Http\Requests;

use App\Domain\Property\PropertyType;
use App\Models\MunicipalitySolarPrice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The guided project form (ADR-0013): kind of property, location, roof, tariff and name.
 * The consumption is not here: it comes from the appliances of the consumption diary.
 */
class SolarProjectRequest extends FormRequest
{
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
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // Chosen when creating; afterwards it is fixed (it defines the diary spaces), so an edit ignores it.
            'property_type' => $this->isMethod('POST') ? ['required', 'string', Rule::in(PropertyType::ALL)] : ['exclude'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'energy_rate_cop_kwh' => ['required', 'numeric', 'gt:0'],
            'available_area_m2' => ['required', 'numeric', 'gt:0'],
            'usable_area_percentage' => ['required', 'numeric', 'between:1,100'],
            'panel_power_w' => ['required', 'numeric', 'gt:0'],
            'panel_area_m2' => ['required', 'numeric', 'gt:0'],
            'system_losses_percentage' => ['required', 'numeric', 'between:0,99'],
            'municipality_id' => ['required', 'integer', Rule::exists('municipalities', 'id')->where('active', true)],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'location_type' => ['required', 'string', Rule::in(MunicipalitySolarPrice::LOCATION_TYPES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'property_type.required' => 'Cuéntanos si es una casa, un negocio o una institución.',
            'property_type.in' => 'Elige una casa, un negocio o una institución.',
            'energy_rate_cop_kwh.gt' => 'La tarifa del kWh debe ser mayor que 0: aparece en tu recibo.',
        ];
    }
}
