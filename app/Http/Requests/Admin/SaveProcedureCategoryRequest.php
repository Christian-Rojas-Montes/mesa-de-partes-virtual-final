<?php

namespace App\Http\Requests\Admin;

use App\Models\ProcedureCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProcedureCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('procedureCategory');

        return $this->user()?->can($category ? 'update' : 'create', $category ?: ProcedureCategory::class) === true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('procedure_categories', 'code')->ignore($this->route('procedureCategory'))],
            'name' => ['required', 'string', 'max:150', Rule::unique('procedure_categories', 'name')->ignore($this->route('procedureCategory'))],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['required', 'integer', 'between:0,65535'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => mb_strtoupper(trim((string) $this->input('code'))), 'name' => trim((string) $this->input('name'))]);
    }
}
