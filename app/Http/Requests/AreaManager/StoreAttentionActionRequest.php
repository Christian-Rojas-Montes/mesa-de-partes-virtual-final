<?php

namespace App\Http\Requests\AreaManager;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttentionActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('attendAssigned', $this->route('procedureRequest')) === true;
    }

    public function rules(): array
    {
        return ['description' => ['required', 'string', 'max:3000']];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['description' => trim((string) $this->input('description'))]);
    }
}
