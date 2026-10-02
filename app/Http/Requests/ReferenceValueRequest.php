<?php

namespace App\Http\Requests;

use App\Domain\Reference\ReferenceValueCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReferenceValueRequest extends FormRequest
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
        $definition = ReferenceValueCatalog::has((string) $this->input('key'))
            ? ReferenceValueCatalog::definition((string) $this->input('key'))
            : null;

        return [
            'key' => ['required', 'string', Rule::in(array_keys(ReferenceValueCatalog::all()))],
            'value' => array_filter(['required', 'numeric', $definition ? 'between:'.$definition->min.','.$definition->max : null]),
            'valid_from' => ['required', 'date'],
            'source' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'value.required' => 'Escribe el nuevo valor.',
            'value.between' => 'El valor debe estar entre :min y :max.',
            'valid_from.required' => 'Indica desde cuándo rige.',
        ];
    }
}
