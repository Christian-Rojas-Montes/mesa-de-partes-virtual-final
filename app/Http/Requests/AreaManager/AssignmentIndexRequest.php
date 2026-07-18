<?php

namespace App\Http\Requests\AreaManager;

use App\Models\ProcedureRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignmentIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAssignedAny', ProcedureRequest::class) === true;
    }

    public function rules(): array
    {
        return [
            'codigo' => ['nullable', 'string', 'max:30'],
            'tramite' => ['nullable', 'integer', 'exists:procedure_types,id'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'recepcion' => ['nullable', Rule::in(['pendiente', 'recibido'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'codigo' => mb_strtoupper(trim((string) $this->input('codigo'))) ?: null,
            'tramite' => $this->input('tramite') ?: null,
            'desde' => $this->input('desde') ?: null,
            'hasta' => $this->input('hasta') ?: null,
            'recepcion' => $this->input('recepcion') ?: null,
        ]);
    }
}
