<?php

namespace App\Http\Requests\Admin;

use App\Enums\DynamicFieldType;
use App\Models\ProcedureDynamicField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProcedureDynamicFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        $model = $this->route('dynamicField');

        return $this->user()?->can($model ? 'update' : 'create', $model ?: ProcedureDynamicField::class) === true;
    }

    public function rules(): array
    {
        return [
            'procedure_variant_id' => ['nullable', 'integer', Rule::exists('procedure_variants', 'id')->where('procedure_type_id', $this->route('procedureType')->id)],
            'key' => ['required', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_]*$/'], 'type' => ['required', Rule::enum(DynamicFieldType::class)], 'label' => ['required', 'string', 'max:150'], 'help_text' => ['nullable', 'string', 'max:2000'], 'required' => ['required', 'boolean'],
            'min_length' => ['nullable', 'integer', 'min:0'], 'max_length' => ['nullable', 'integer', 'gte:min_length'], 'min_value' => ['nullable', 'numeric'], 'max_value' => ['nullable', 'numeric', 'gte:min_value'], 'options_json' => ['nullable', 'json'], 'validation_rule' => ['nullable', 'string', 'max:500'], 'visibility_conditions_json' => ['nullable', 'json'], 'sort_order' => ['required', 'integer', 'between:0,65535'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['key' => trim(mb_strtolower((string) $this->input('key'))), 'required' => $this->boolean('required')]);
    }

    public function payload(): array
    {
        $data = $this->validated();
        $data['options'] = filled($data['options_json'] ?? null) ? json_decode($data['options_json'], true, 512, JSON_THROW_ON_ERROR) : null;
        $data['visibility_conditions'] = filled($data['visibility_conditions_json'] ?? null) ? json_decode($data['visibility_conditions_json'], true, 512, JSON_THROW_ON_ERROR) : [];
        unset($data['options_json'], $data['visibility_conditions_json']);

        return $data;
    }
}
