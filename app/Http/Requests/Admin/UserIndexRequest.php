<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', User::class) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'buscar' => ['nullable', 'string', 'max:100'],
            'rol' => ['nullable', 'integer', 'exists:roles,id'],
            'area' => ['nullable', 'integer', 'exists:areas,id'],
            'estado' => ['nullable', Rule::in(['1', '0'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'buscar' => trim((string) $this->input('buscar')) ?: null,
            'rol' => $this->input('rol') ?: null,
            'area' => $this->input('area') ?: null,
            'estado' => $this->input('estado') === '' ? null : $this->input('estado'),
        ]);
    }
}
