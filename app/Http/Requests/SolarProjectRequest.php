<?php

namespace App\Http\Requests;

use App\Models\MunicipalitySolarPrice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SolarProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
            'performance_ratio' => ['required', 'numeric', 'between:0,1'],
            'system_losses_percentage' => ['required', 'numeric', 'between:0,100'],
            'municipality_id' => ['required', 'integer', Rule::exists('municipalities', 'id')->where('active', true)],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'location_type' => ['required', 'string', Rule::in(MunicipalitySolarPrice::LOCATION_TYPES)],
            'required_power_kw' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
