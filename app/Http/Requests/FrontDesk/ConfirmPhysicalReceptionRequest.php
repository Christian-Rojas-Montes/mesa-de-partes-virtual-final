<?php

namespace App\Http\Requests\FrontDesk;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmPhysicalReceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('confirmPhysicalReception', $this->route('procedureRequest')) === true;
    }

    public function rules(): array
    {
        return [
            'received_at' => ['required', 'date', 'before_or_equal:now'], 'folio_count' => ['nullable', 'integer', 'between:1,100000'],
            'document_count' => ['required', 'integer', 'between:1,1000'], 'presented_documents' => ['required', 'array', 'min:1', 'max:1000'],
            'presented_documents.*.name' => ['required', 'string', 'max:200'], 'presented_documents.*.presentation' => ['required', Rule::in(['original', 'copy'])], 'presented_documents.*.quantity' => ['required', 'integer', 'between:1,1000'],
            'observations' => ['nullable', 'string', 'max:5000'], 'receiving_area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'receipt_number' => ['nullable', 'string', 'max:100', 'unique:request_physical_receptions,receipt_number'],
            'verification_result' => ['required', Rule::in(['complete', 'incomplete', 'originals_verified'])],
        ];
    }
}
