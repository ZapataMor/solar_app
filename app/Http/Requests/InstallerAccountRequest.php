<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * The account an administrator gives an allied installer (ADR-0023).
 */
class InstallerAccountRequest extends FormRequest
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
        $account = $this->route('installer')?->account;
        $ignore = $account?->id;

        return [
            'account_name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:60', 'alpha_dash', Rule::unique('users', 'username')->ignore($ignore)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($ignore)],
            // Only on the first save: editing without touching it keeps the one in use. No
            // confirmation field: the administrator writes it once and passes it on to the installer.
            'password' => [$account === null ? 'required' : 'nullable', 'string', Password::default()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'account_name.required' => 'Escribe el nombre de quien usará la cuenta.',
            'username.required' => 'Elige un usuario para entrar.',
            'username.unique' => 'Ese usuario ya está tomado.',
            'email.unique' => 'Ese correo ya tiene una cuenta.',
            'password.required' => 'Escribe una contraseña para la primera entrada.',
        ];
    }
}
