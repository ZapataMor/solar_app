<?php

namespace App\Http\Requests;

use App\Domain\Consumption\ApplianceCatalog;
use App\Domain\Property\PropertyType;
use App\Models\SolarProject;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One appliance of the consumption diary (ADR-0013). Authorization is done by the controller
 * through the "manage" policy of the project.
 */
class ProjectApplianceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Decimal comma from the browser ("1,5" hours).
        if (is_string($this->input('hours_per_day'))) {
            $this->merge(['hours_per_day' => str_replace(',', '.', $this->input('hours_per_day'))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var SolarProject $solarProject */
        $solarProject = $this->route('solarProject');

        return [
            'space' => ['required', 'string', Rule::in(PropertyType::spaceKeys($solarProject->property_type))],
            'key' => ['required', 'string', Rule::in(array_keys((new ApplianceCatalog)->all()))],
            'variant' => ['required', 'string', $this->variantRule()],
            'quantity' => ['required', 'integer', 'between:1,100'],
            'hours_per_day' => ['required', 'numeric', 'between:0,24'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'space.in' => 'Ese espacio no existe en este proyecto.',
            'key.in' => 'Ese equipo no está en el catálogo.',
            'quantity.between' => 'La cantidad debe estar entre 1 y 100.',
            'hours_per_day.between' => 'Las horas de uso deben estar entre 0 y 24 al día.',
        ];
    }

    /**
     * The chosen option must exist for the chosen appliance.
     */
    private function variantRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! (new ApplianceCatalog)->hasVariant((string) $this->input('key'), (string) $value)) {
                $fail('Elige una opción válida para ese equipo.');
            }
        };
    }
}
