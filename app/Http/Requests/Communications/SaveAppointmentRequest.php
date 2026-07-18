<?php

namespace App\Http\Requests\Communications;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageCommunications', $this->route('procedureRequest')) === true;
    }

    public function rules(): array
    {
        return ['appointment_date' => ['required', 'date', 'after_or_equal:today'], 'starts_at' => ['required', 'date_format:H:i'], 'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'], 'office' => ['required', 'string', 'max:200'], 'area_id' => ['nullable', 'exists:areas,id'], 'reference_person' => ['nullable', 'string', 'max:150'], 'reason' => ['required', 'string', 'max:500'], 'instructions' => ['nullable', 'string', 'max:2000'], 'deadline' => ['nullable', 'date', 'after_or_equal:today'], 'status' => ['nullable', Rule::in(['scheduled', 'confirmed'])]];
    }
}
