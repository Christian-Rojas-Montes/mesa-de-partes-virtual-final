<?php

namespace App\Http\Requests\Admin;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('user')) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')->where('active', true)],
            'area_id' => ['nullable', 'integer', Rule::exists('areas', 'id')->where('active', true)],
            'document_type' => ['required', Rule::in(['DNI', 'CE', 'PASAPORTE'])],
            'document_number' => ['required', 'string', 'max:30', Rule::unique('users', 'document_number')->ignore($this->route('user'))],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'lowercase', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'phone' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $role = Role::find($this->integer('role_id'));

            if ($role?->name === 'Responsable de área' && ! $this->filled('area_id')) {
                $validator->errors()->add('area_id', 'Debes asignar un área al responsable de área.');
            }

            if ($role?->name === 'Solicitante' && $this->filled('area_id')) {
                $validator->errors()->add('area_id', 'Un solicitante no puede estar asociado a un área interna.');
            }

            if ($this->user()->is($this->route('user')) && $role?->name !== 'Administrador') {
                $validator->errors()->add('role_id', 'No puedes retirar tu propio rol de administrador.');
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
