<?php

namespace App\Http\Requests\FrontDesk;

use App\Models\ProcedureRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePhysicalProcedureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('createPhysical', ProcedureRequest::class) === true;
    }

    public function rules(): array
    {
        $new = ! $this->filled('existing_user_id');

        return [
            'existing_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'confirm_existing_identity' => [$new ? 'nullable' : 'accepted'],
            'document_type' => [$new ? 'required' : 'nullable', Rule::in(['DNI', 'CE', 'PASAPORTE', 'OTRO'])], 'document_number' => [$new ? 'required' : 'nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9-]+$/'],
            'student_code' => ['nullable', 'string', 'max:50'], 'first_name' => [$new ? 'required' : 'nullable', 'string', 'max:100'], 'last_name' => [$new ? 'required' : 'nullable', 'string', 'max:100'],
            'academic_program' => [$new ? 'required' : 'nullable', 'string', 'max:150'], 'academic_condition' => [$new ? 'required' : 'nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:30'],
            'procedure_type_id' => ['required', 'integer', Rule::exists('procedure_types', 'id')->where('active', true)], 'procedure_variant_id' => ['nullable', 'integer'],
            'received_at' => ['required', 'date', 'before_or_equal:now'], 'folio_count' => ['nullable', 'integer', 'between:1,100000'], 'receiving_area_id' => ['nullable', 'integer', 'exists:areas,id'], 'observations' => ['nullable', 'string', 'max:5000'],
            'received_documents' => ['nullable', 'array'], 'received_documents.*.received' => ['nullable', 'boolean'], 'received_documents.*.presentation' => ['required_if:received_documents.*.received,1', Rule::in(['original', 'simple_copy', 'legalized_copy', 'endorsed_copy'])], 'received_documents.*.quantity' => ['required_if:received_documents.*.received,1', 'nullable', 'integer', 'between:1,1000'],
        ];
    }
}
