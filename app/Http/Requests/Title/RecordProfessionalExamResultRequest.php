<?php

namespace App\Http\Requests\Title;

use Illuminate\Foundation\Http\FormRequest;

class RecordProfessionalExamResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['result' => ['required', 'in:approved,failed,absent,rescheduled'], 'theory_grade' => ['nullable', 'numeric', 'between:0,20'], 'practical_grade' => ['nullable', 'numeric', 'between:0,20'], 'observation' => ['nullable', 'string', 'max:2000']];
    }
}
