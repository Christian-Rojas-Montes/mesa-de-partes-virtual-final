<?php

namespace App\Http\Requests\Title;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleTitleProcessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', $this->route('titleProcess')) === true;
    }

    public function rules(): array
    {
        return ['scheduled_at' => ['required', 'date'], 'place' => ['required', 'string', 'max:200'], 'jury_or_responsibles' => ['nullable', 'array', 'max:10'], 'jury_or_responsibles.*' => ['string', 'max:150'], 'reason' => ['nullable', 'string', 'max:1000']];
    }
}
