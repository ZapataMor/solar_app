<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The quote an installer offers for a request (ADR-0026) with the detail of a real one (ADR-0027).
 *
 * Only the total and the validity are required: the rest is what makes it comparable, and an
 * installer answering from a phone should be able to send the price first and complete it after.
 */
class InstallerQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('answer-quote-requests') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // A system costs millions of pesos; anything under a million is a missing zero.
            'amount_cop' => ['required', 'numeric', 'between:1000000,999999999999'],
            // Imported equipment follows the dollar: an offer without an end is not an offer.
            'valid_until' => ['required', 'date', 'after_or_equal:today'],

            'power_kw' => ['nullable', 'numeric', 'between:0.1,10000'],
            'panel_count' => ['nullable', 'integer', 'between:1,10000'],
            // Today's modules run from 300 W up; 2000 W leaves room for whatever comes next.
            'panel_watts' => ['nullable', 'integer', 'between:50,2000'],
            'panel_model' => ['nullable', 'string', 'max:120'],
            'inverter_model' => ['nullable', 'string', 'max:120'],
            'includes_battery' => ['nullable', 'boolean'],
            'battery_kwh' => ['nullable', 'numeric', 'between:0.1,1000'],
            'monthly_generation_kwh' => ['nullable', 'numeric', 'between:1,1000000'],

            'includes_retie' => ['nullable', 'boolean'],
            'includes_grid_paperwork' => ['nullable', 'boolean'],
            'includes_bidirectional_meter' => ['nullable', 'boolean'],
            'includes_maintenance' => ['nullable', 'boolean'],

            // A panel warranty of 30 years is the longest anyone offers today.
            'panel_warranty_years' => ['nullable', 'integer', 'between:1,40'],
            'inverter_warranty_years' => ['nullable', 'integer', 'between:1,40'],
            'workmanship_warranty_years' => ['nullable', 'integer', 'between:1,40'],

            'vat_included' => ['nullable', 'boolean'],
            'down_payment_percentage' => ['nullable', 'integer', 'between:0,100'],
            'delivery_days' => ['nullable', 'integer', 'between:1,730'],
            'scope' => ['nullable', 'string', 'max:1000'],
            'exclusions' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount_cop.required' => 'Escribe cuánto cuesta la instalación.',
            'amount_cop.between' => 'El valor debe estar entre $1.000.000 y $999.999.999.999.',
            'valid_until.required' => 'Di hasta cuándo vale este precio.',
            'valid_until.after_or_equal' => 'La fecha de validez no puede ser anterior a hoy.',
            'panel_watts.between' => 'La potencia de cada panel va entre 50 W y 2.000 W.',
            'panel_warranty_years.between' => 'La garantía de los paneles va entre 1 y 40 años.',
            'inverter_warranty_years.between' => 'La garantía del inversor va entre 1 y 40 años.',
            'workmanship_warranty_years.between' => 'La garantía de la obra va entre 1 y 40 años.',
            'down_payment_percentage.between' => 'El anticipo va entre 0 % y 100 %.',
            'delivery_days.between' => 'El plazo va entre 1 y 730 días.',
        ];
    }
}
