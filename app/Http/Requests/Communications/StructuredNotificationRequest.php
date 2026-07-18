<?php

namespace App\Http\Requests\Communications;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StructuredNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageCommunications', $this->route('procedureRequest')) === true;
    }

    public function rules(): array
    {
        return ['type' => ['required', Rule::in(['request_registered', 'physical_reception_confirmed', 'physical_documents_incomplete', 'request_observed', 'correction_submitted', 'request_validated', 'request_rejected', 'request_derived', 'attention_started', 'originals_required', 'request_finalized'])], 'message' => ['required', 'string', 'max:1000']];
    }
}
