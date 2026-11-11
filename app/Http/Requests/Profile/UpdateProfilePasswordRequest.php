<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateProfilePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)->mixedCase()->letters()->numbers()->symbols()],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'current_password.current_password' => 'La contraseña actual no es correcta.',
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
            'password.different' => 'La nueva contraseña debe ser diferente de la actual.',
        ];
    }
}
