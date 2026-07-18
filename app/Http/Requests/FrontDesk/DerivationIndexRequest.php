<?php

namespace App\Http\Requests\FrontDesk;

use App\Models\ProcedureRequest;
use Illuminate\Foundation\Http\FormRequest;

class DerivationIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('deriveAny', ProcedureRequest::class) === true;
    }

    public function rules(): array
    {
        return [
            'codigo' => ['nullable', 'string', 'max:30'],
            'tramite' => ['nullable', 'integer', 'exists:procedure_types,id'],
            'area' => ['nullable', 'integer', 'exists:areas,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'codigo' => mb_strtoupper(trim((string) $this->input('codigo'))) ?: null,
            'tramite' => $this->input('tramite') ?: null,
            'area' => $this->input('area') ?: null,
        ]);
    }
}
