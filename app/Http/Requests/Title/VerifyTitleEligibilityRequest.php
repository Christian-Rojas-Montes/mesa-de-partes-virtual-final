<?php

namespace App\Http\Requests\Title;

use Illuminate\Foundation\Http\FormRequest;

class VerifyTitleEligibilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', $this->route('titleProcess')) === true;
    }

    public function rules(): array
    {
        return [
            'graduate_status' => ['required', 'boolean'], 'language' => ['required', 'boolean'],
            'practice_efsrt' => ['required', 'boolean'], 'observation' => ['nullable', 'string', 'max:1000'],
            'responsible_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
