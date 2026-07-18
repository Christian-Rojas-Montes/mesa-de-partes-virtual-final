<?php

namespace App\Http\Requests\Title;

use Illuminate\Foundation\Http\FormRequest;

class CreateProfessionalExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['experience_months' => ['required', 'integer', 'min:0'], 'experience_basis' => ['required', 'in:professional,efsrt']];
    }
}
