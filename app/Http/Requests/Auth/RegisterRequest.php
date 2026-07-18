<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_type' => ['required', Rule::in(['DNI', 'CE', 'PASAPORTE', 'OTRO'])],
            'document_number' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9-]+$/', 'unique:users,document_number'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->letters()->numbers()->symbols()],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => Str::lower((string) $this->input('email'))]);
    }

    public function messages(): array
    {
        return [
            'document_type.required' => 'Selecciona un tipo de documento.',
            'document_type.in' => 'El tipo de documento seleccionado no es válido.',
            'document_number.required' => 'Ingresa el número de documento.',
            'document_number.regex' => 'El documento solo puede contener letras, números y guiones.',
            'document_number.unique' => 'El número de documento ya está registrado.',
            'first_name.required' => 'Ingresa los nombres.',
            'last_name.required' => 'Ingresa los apellidos.',
            'email.required' => 'Ingresa un correo electrónico.',
            'email.email' => 'Ingresa un correo electrónico válido.',
            'email.unique' => 'El correo electrónico ya está registrado.',
            'password.required' => 'Ingresa una contraseña.',
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
        ];
    }
}
