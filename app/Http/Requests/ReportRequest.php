<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->active === true
            && $this->user()?->role?->active === true
            && in_array($this->user()?->role?->name, [
                'Solicitante', 'Mesa de Partes', 'Responsable de área', 'Administrador', 'Personal académico',
            ], true);
    }

    public function rules(): array
    {
        return [
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'desde' => $this->input('desde') ?: null,
            'hasta' => $this->input('hasta') ?: null,
        ]);
    }
}
