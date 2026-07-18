<?php

namespace App\Http\Requests\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')->where('active', true)],
            'area_id' => ['nullable', 'integer', Rule::exists('areas', 'id')->where('active', true)],
            'document_type' => ['required', Rule::in(['DNI', 'CE', 'PASAPORTE'])],
            'document_number' => ['required', 'string', 'max:30', 'unique:users,document_number'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'lowercase', 'email:rfc', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $role = Role::find($this->integer('role_id'));

            if ($role?->name === 'Solicitante') {
                $validator->errors()->add('role_id', 'La creación administrativa está limitada a usuarios internos.');
            }

            if ($role?->name === 'Responsable de área' && ! $this->filled('area_id')) {
                $validator->errors()->add('area_id', 'Debes asignar un área al responsable de área.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'area_id' => $this->input('area_id') ?: null,
            'document_type' => mb_strtoupper(trim((string) $this->input('document_type'))),
            'document_number' => mb_strtoupper(trim((string) $this->input('document_number'))),
            'first_name' => trim((string) $this->input('first_name')),
            'last_name' => trim((string) $this->input('last_name')),
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'phone' => trim((string) $this->input('phone')) ?: null,
        ]);
    }
}
