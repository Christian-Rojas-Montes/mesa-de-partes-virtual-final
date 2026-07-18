<?php

namespace App\Http\Requests\Title;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordTitleResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', $this->route('titleProcess')) === true;
    }

    public function rules(): array
    {
        return ['result' => ['required', Rule::in(['approved', 'failed', 'absent', 'rescheduled', 'observed'])], 'observation' => ['nullable', 'string', 'max:2000']];
    }
}
