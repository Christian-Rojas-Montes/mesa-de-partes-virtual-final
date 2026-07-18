<?php

namespace App\Http\Requests\Title;

use Illuminate\Foundation\Http\FormRequest;

class ReviewFinalDossierRequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['status' => ['required', 'in:presented,verified,missing,observed,subsanated,not_applicable'], 'request_document_id' => ['nullable', 'integer', 'exists:request_documents,id'], 'observation' => ['nullable', 'string', 'max:2000']];
    }
}
