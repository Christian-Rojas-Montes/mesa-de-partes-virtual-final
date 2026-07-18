<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VariantSelectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'variant_code' => ['nullable', 'string', Rule::exists('procedure_variants', 'code')->where('procedure_type_id', $this->route('procedureType')->id)->where('active', true)],
            'answers' => ['present', 'array', 'max:20'],
            'answers.*' => ['nullable', 'string', 'max:255'],
        ];
    }
}
