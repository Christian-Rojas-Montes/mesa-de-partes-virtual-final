<?php

namespace App\Http\Requests\FrontDesk;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDerivationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('derive', $this->route('procedureRequest')) === true;
    }

    public function rules(): array
    {
        return [
            'area_id' => ['required', 'integer', Rule::exists('areas', 'id')->where('active', true)],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['reason' => trim((string) $this->input('reason')) ?: null]);
    }
}
