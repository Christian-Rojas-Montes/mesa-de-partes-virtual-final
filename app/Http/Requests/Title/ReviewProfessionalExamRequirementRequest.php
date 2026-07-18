<?php

namespace App\Http\Requests\Title;

use Illuminate\Foundation\Http\FormRequest;

class ReviewProfessionalExamRequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', $this->route('titleProcess')) === true;
    }

    public function rules(): array
    {
        return ['code' => ['required', 'string', 'max:60'], 'status' => ['required', 'in:verified,missing,observed'], 'request_document_id' => ['nullable', 'integer', 'exists:request_documents,id'], 'observation' => ['nullable', 'string', 'max:2000']];
    }
}
