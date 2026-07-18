<?php

namespace App\Http\Requests\Communications;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AppointmentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageCommunications', $this->route('procedureRequest')) === true;
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::in(['confirmed', 'attended', 'cancelled'])]];
    }
}
