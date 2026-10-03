<?php

namespace App\Http\Requests;

use App\Domain\Consumption\ConsumptionMode;
use App\Domain\Property\PropertyType;
use App\Domain\Solar\AnalysisPeriod;
use App\Models\MunicipalitySolarPrice;
use App\Models\SolarProject;
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
        // The form always asks how the consumption is given (ADR-0020). A request without it keeps what the
        // project already has, or the diary of appliances for a new one.
        if (! $this->filled('consumption_mode')) {
            $project = $this->route('solarProject');
            $this->merge(['consumption_mode' => $project instanceof SolarProject ? ConsumptionMode::normalize($project->consumption_mode) : ConsumptionMode::APPLIANCES]);

            if ($project instanceof SolarProject && $project->usesBillConsumption() && ! $this->filled('monthly_consumption_kwh')) {
                $this->merge(['monthly_consumption_kwh' => $project->monthlyConsumption()]);
            }
        }

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
            // From the appliances (the diary) or from the bill: only the bill asks for the kWh here (ADR-0020).
            'consumption_mode' => ['required', 'string', Rule::in(ConsumptionMode::ALL)],
            'monthly_consumption_kwh' => ['exclude_unless:consumption_mode,'.ConsumptionMode::BILL, 'required', 'numeric', 'between:'.ConsumptionMode::MIN_BILL_KWH.','.ConsumptionMode::MAX_BILL_KWH],
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
            'consumption_mode.in' => 'Elige si calculamos tu consumo con tus equipos o con tu recibo.',
            'monthly_consumption_kwh.required' => 'Escribe los kWh al mes que aparecen en tu recibo.',
            'monthly_consumption_kwh.numeric' => 'Escribe solo el número de kWh al mes de tu recibo.',
            'monthly_consumption_kwh.between' => 'Los kWh al mes deben estar entre :min y :max. Míralos en tu recibo.',
            'energy_rate_cop_kwh.gt' => 'La tarifa del kWh debe ser mayor que 0: aparece en tu recibo.',
            'start_date.date' => 'La fecha inicial del análisis no es una fecha válida.',
            'end_date.date' => 'La fecha final del análisis no es una fecha válida.',
            'end_date.after_or_equal' => 'La fecha final del análisis debe ser posterior a la inicial.',
            'end_date.before_or_equal' => 'La fecha final del análisis no puede ser posterior a hoy: esos días aún no tienen datos del clima.',
        ];
    }
}
