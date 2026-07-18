<?php

namespace App\Http\Requests\Title;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplicationWorkOriginalityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', $this->route('titleProcess')) === true;
    }

    public function rules(): array
    {
        return ['similarity_percent' => ['required', 'numeric', 'between:0,100'], 'originality_result' => ['required', Rule::in(['conforming', 'observed'])]];
    }
}
