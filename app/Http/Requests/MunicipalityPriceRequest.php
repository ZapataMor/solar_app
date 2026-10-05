<?php

namespace App\Http\Requests;

use App\Actions\Pricing\DescribeMunicipalityPrices;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The price per kW of a municipality, as an administrator writes it (ADR-0024).
 */
class MunicipalityPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('administer-platform') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'municipality_id' => ['required', Rule::exists('municipalities', 'id')],
            'location_type' => ['required', Rule::in(array_keys(DescribeMunicipalityPrices::LOCATION_TYPES))],
            // A system costs millions of pesos per kW: anything outside this is a typo, and a typo
            // here is the most expensive one in the app.
            'base_price_per_kw' => ['required', 'numeric', 'between:500000,20000000'],
            // What getting there costs on top: 1 is the municipality's own price, 1,3 is a third more.
            'logistic_factor' => ['required', 'numeric', 'between:1,2.5'],
            'notes' => ['nullable', 'string', 'max:255'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'base_price_per_kw.required' => 'Escribe el precio base por kW.',
            'base_price_per_kw.between' => 'El precio por kW debe estar entre $500.000 y $20.000.000.',
            'logistic_factor.between' => 'El factor logístico va de 1 (sin recargo) a 2,5.',
        ];
    }
}
