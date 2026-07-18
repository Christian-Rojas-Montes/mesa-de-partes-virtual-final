<?php

namespace App\Http\Requests\FrontDesk;

use Illuminate\Foundation\Http\FormRequest;

class AssignAcademicFileNumberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('review', $this->route('procedureRequest')) === true;
    }

    public function rules(): array
    {
        return ['academic_file_number' => ['required', 'string', 'max:100']];
    }

    public function messages(): array
    {
        return ['academic_file_number.required' => 'Ingresa el número de expediente académico o externo.'];
    }
}
