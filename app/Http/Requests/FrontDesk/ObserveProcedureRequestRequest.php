<?php

namespace App\Http\Requests\FrontDesk;

use Illuminate\Foundation\Http\FormRequest;

class ObserveProcedureRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('observe', $this->route('procedureRequest')) === true;
    }

    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:2000'],
            'correction_instructions' => ['nullable', 'string', 'max:2000'],
            'correction_deadline' => ['nullable', 'date', 'after:today'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'description' => trim((string) $this->input('description')),
            'correction_instructions' => trim((string) $this->input('correction_instructions')) ?: null,
            'correction_deadline' => $this->input('correction_deadline') ?: null,
        ]);
    }
}
