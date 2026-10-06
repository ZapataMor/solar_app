<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The price an installer offers for a request (ADR-0026).
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
            'power_kw' => ['nullable', 'numeric', 'between:0.1,10000'],
            'includes_battery' => ['nullable', 'boolean'],
            'scope' => ['nullable', 'string', 'max:1000'],
            // Imported equipment follows the dollar: an offer without an end is not an offer.
            'valid_until' => ['required', 'date', 'after_or_equal:today'],
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
        ];
    }
}
