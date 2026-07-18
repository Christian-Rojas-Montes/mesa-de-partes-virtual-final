<?php

namespace App\Http\Requests\FrontDesk;

use App\Models\ProcedureRequest;
use Illuminate\Foundation\Http\FormRequest;

class ReviewIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reviewAny', ProcedureRequest::class) === true;
    }

    public function rules(): array
    {
        return [
            'codigo' => ['nullable', 'string', 'max:30'],
            'fecha' => ['nullable', 'date_format:Y-m-d'],
            'tramite' => ['nullable', 'integer', 'exists:procedure_types,id'],
            'estado' => ['nullable', 'integer', 'exists:statuses,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'codigo' => mb_strtoupper(trim((string) $this->input('codigo'))) ?: null,
            'fecha' => $this->input('fecha') ?: null,
            'tramite' => $this->input('tramite') ?: null,
            'estado' => $this->input('estado') ?: null,
        ]);
    }
}
