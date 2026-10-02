<?php

namespace App\Http\Requests;

use App\Domain\Consumption\ApplianceCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An appliance an administrator adds to or changes in the catalog (ADR-0017).
 */
class CatalogApplianceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('administer-platform') ?? false;
    }

    protected function prepareForValidation(): void
    {
        // Empty rows of the options table are not options.
        $this->merge([
            'variants' => array_values(array_filter(
                (array) $this->input('variants', []),
                fn ($row) => is_array($row) && (filled($row['label'] ?? null) || filled($row['watts'] ?? null)),
            )),
            // New appliances are offered right away; the edit form sends 0/1 explicitly.
            'active' => $this->has('active') ? $this->boolean('active') : true,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $several = count((array) $this->input('variants', [])) > 1;

        return [
            'label' => ['required', 'string', 'max:80'],
            'icon' => ['required', 'string', Rule::in(array_keys(ApplianceCatalog::ICONS))],
            'segments' => ['required', 'array', 'min:1'],
            'segments.*' => ['string', Rule::in([ApplianceCatalog::SEGMENT_HOME, ApplianceCatalog::SEGMENT_BUSINESS])],
            'usage' => ['required', Rule::in(['day', 'week', 'always'])],
            'default_hours' => ['exclude_if:usage,always', 'required', 'numeric', 'gt:0', $this->input('usage') === 'week' ? 'max:168' : 'max:24'],
            'default_quantity' => ['required', 'integer', 'between:1,100'],
            'hint' => ['nullable', 'string', 'max:255'],
            'option_label' => ['nullable', 'string', 'max:40'],
            'variants' => ['required', 'array', 'min:1', 'max:8'],
            'variants.*.key' => ['nullable', 'string', 'max:60'],
            'variants.*.label' => [$several ? 'required' : 'nullable', 'string', 'max:40'],
            'variants.*.watts' => ['required', 'numeric', 'gt:0', 'max:20000'],
            'active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'label.required' => 'Escribe el nombre del equipo.',
            'segments.required' => 'Elige si es para hogar, negocio o ambos.',
            'default_hours.required' => 'Indica cuántas horas se usa normalmente.',
            'default_hours.max' => 'Las horas no pueden pasar de :max.',
            'variants.required' => 'Agrega al menos una opción con su potencia.',
            'variants.*.label.required' => 'Cada opción necesita un nombre (por ejemplo, "2 ranuras").',
            'variants.*.watts.required' => 'Cada opción necesita su potencia en vatios.',
            'variants.*.watts.gt' => 'La potencia debe ser mayor que 0.',
        ];
    }
}
