<?php

namespace App\Http\Requests;

use App\Domain\Property\PropertyType;
use App\Domain\Solar\AnalysisPeriod;
use App\Models\MunicipalitySolarPrice;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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

    /**
     * "Today" for the analysis period is the client's day (Bogotá), not the server's UTC one.
     */
    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(config('app.display_timezone', config('app.timezone')))->startOfDay();
    }

    protected function prepareForValidation(): void
    {
        // Missing dates take the default period: the last three months up to today (AnalysisPeriod).
        if (! $this->filled('end_date')) {
            $this->merge(['end_date' => self::today()->toDateString()]);
        }

        $end = date_create_immutable((string) $this->input('end_date'));

        if (! $this->filled('start_date') && $end !== false) {
            $this->merge(['start_date' => AnalysisPeriod::defaultStart($end)->format('Y-m-d')]);
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
            // There is no climate data after today; the minimum month is checked in after().
            'end_date' => ['required', 'date', 'after_or_equal:start_date', 'before_or_equal:'.self::today()->toDateString()],
            // Optional (ADR-0015): empty means the project follows the reference tariff of the system.
            'energy_rate_cop_kwh' => ['nullable', 'numeric', 'gt:0'],
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
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['start_date', 'end_date'])) {
                    return;
                }

                $end = new DateTimeImmutable((string) $this->input('end_date'));

                if (! AnalysisPeriod::isLongEnough(new DateTimeImmutable((string) $this->input('start_date')), $end)) {
                    $validator->errors()->add('start_date', sprintf(
                        'El periodo de análisis debe cubrir al menos un mes: hasta el %s, empieza el %s o antes.',
                        $end->format('d/m/Y'),
                        AnalysisPeriod::latestStart($end)->format('d/m/Y'),
                    ));
                }
            },
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
            'start_date.date' => 'La fecha inicial del análisis no es una fecha válida.',
            'end_date.date' => 'La fecha final del análisis no es una fecha válida.',
            'end_date.after_or_equal' => 'La fecha final del análisis debe ser posterior a la inicial.',
            'end_date.before_or_equal' => 'La fecha final del análisis no puede ser posterior a hoy: esos días aún no tienen datos del clima.',
        ];
    }
}
