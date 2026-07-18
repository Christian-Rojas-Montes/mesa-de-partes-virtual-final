<?php

namespace App\Http\Requests\FrontDesk;

use App\Models\ProcedureRequest;
use Illuminate\Foundation\Http\FormRequest;

class ClosureIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('closeAny', ProcedureRequest::class) === true;
    }

    public function rules(): array
    {
        return ['codigo' => ['nullable', 'string', 'max:30']];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['codigo' => mb_strtoupper(trim((string) $this->input('codigo'))) ?: null]);
    }
}
