<?php

namespace App\Http\Requests\Title;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApproveApplicationWorkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', $this->route('titleProcess')) === true;
    }

    public function rules(): array
    {
        return ['review_result' => ['required', Rule::in(['approved', 'observed', 'rejected'])], 'approval_observations' => ['nullable', 'string', 'max:3000'], 'approval_resolution_document_id' => ['nullable', 'integer', 'exists:request_documents,id'], 'assigned_advisor' => ['nullable', 'string', 'max:200'], 'approved_at' => ['nullable', 'date'], 'execution_deadline' => ['nullable', 'date', 'after:approved_at']];
    }
}
