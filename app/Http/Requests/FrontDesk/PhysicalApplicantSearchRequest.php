<?php

namespace App\Http\Requests\FrontDesk;

use App\Models\ProcedureRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PhysicalApplicantSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('createPhysical', ProcedureRequest::class) === true;
    }

    public function rules(): array
    {
        return ['document_type' => ['nullable', Rule::in(['DNI', 'CE', 'PASAPORTE', 'OTRO']), 'required_with:document_number'], 'document_number' => ['nullable', 'string', 'max:30'], 'email' => ['nullable', 'email', 'max:255'], 'student_code' => ['nullable', 'string', 'max:50']];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => Str::lower(trim((string) $this->input('email'))), 'document_number' => trim((string) $this->input('document_number')), 'student_code' => trim((string) $this->input('student_code'))]);
    }
}
