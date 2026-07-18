<?php

namespace App\Http\Requests\FrontDesk;

use Illuminate\Foundation\Http\FormRequest;

class RejectProcedureRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reject', $this->route('procedureRequest')) === true;
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:2000']];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['reason' => trim((string) $this->input('reason'))]);
    }
}
