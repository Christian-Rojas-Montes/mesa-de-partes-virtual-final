<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AuditLogIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->active
            && $this->user()?->role?->active
            && $this->user()?->role?->name === 'Administrador';
    }

    public function rules(): array
    {
        return [
            'usuario' => ['nullable', 'string', 'max:150'],
            'accion' => ['nullable', 'string', 'max:40'],
            'entidad' => ['nullable', 'string', 'max:100'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'usuario' => trim((string) $this->input('usuario')) ?: null,
            'accion' => $this->input('accion') ?: null,
            'entidad' => $this->input('entidad') ?: null,
            'desde' => $this->input('desde') ?: null,
            'hasta' => $this->input('hasta') ?: null,
        ]);
    }
}
