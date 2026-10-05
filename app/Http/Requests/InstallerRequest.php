<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An allied installer, as an administrator writes it (ADR-0021).
 */
class InstallerRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            // The client only sees them after asking for a quote, but one of the two must exist:
            // a lead nobody can answer is worth nothing.
            'phone' => ['nullable', 'string', 'max:40', 'required_without:email'],
            'email' => ['nullable', 'email', 'max:120', 'required_without:phone'],
            'years_experience' => ['nullable', 'integer', 'between:0,80'],
            'municipalities' => ['required', 'array', 'min:1'],
            'municipalities.*' => [Rule::exists('municipalities', 'id')],
            'active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Escribe el nombre del instalador.',
            'phone.required_without' => 'Deja al menos un teléfono o un correo de contacto.',
            'email.required_without' => 'Deja al menos un teléfono o un correo de contacto.',
            'municipalities.required' => 'Elige al menos un municipio que cubra.',
            'municipalities.min' => 'Elige al menos un municipio que cubra.',
        ];
    }
}
