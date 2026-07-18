<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PublicProcedureCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['buscar' => ['nullable', 'string', 'max:100'], 'categoria' => ['nullable', 'string', 'max:30', 'exists:procedure_categories,code'], 'modalidad' => ['nullable', 'string', 'max:30', 'exists:presentation_modalities,code'], 'publico' => ['nullable', 'string', 'max:100']];
    }
}
