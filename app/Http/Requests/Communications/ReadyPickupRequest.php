<?php

namespace App\Http\Requests\Communications;

use Illuminate\Foundation\Http\FormRequest;

class ReadyPickupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageCommunications', $this->route('procedureRequest')) === true;
    }

    public function rules(): array
    {
        return ['available_at' => ['required', 'date'], 'office' => ['required', 'string', 'max:200'], 'pickup_requirement' => ['nullable', 'string', 'max:500'], 'observation' => ['nullable', 'string', 'max:2000']];
    }
}
