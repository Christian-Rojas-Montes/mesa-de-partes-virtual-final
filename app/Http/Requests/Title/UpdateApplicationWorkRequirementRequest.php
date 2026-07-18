<?php

namespace App\Http\Requests\Title;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateApplicationWorkRequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', $this->route('titleProcess')) === true;
    }

    public function rules(): array
    {
        return ['stage' => ['required', Rule::in(['graduate_certificate', 'defense_file'])], 'code' => ['required', 'string', 'max:60'], 'status' => ['required', Rule::in(['presented', 'verified', 'missing'])], 'request_document_id' => ['nullable', 'integer', 'exists:request_documents,id']];
    }
}
