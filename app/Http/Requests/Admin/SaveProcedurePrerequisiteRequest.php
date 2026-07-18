<?php

namespace App\Http\Requests\Admin;

use App\Enums\PrerequisiteType;
use App\Models\ProcedurePrerequisite;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProcedurePrerequisiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $model = $this->route('prerequisite');

        return $this->user()?->can($model ? 'update' : 'create', $model ?: ProcedurePrerequisite::class) === true;
    }

    public function rules(): array
    {
        return [
            'procedure_variant_id' => ['nullable', 'integer', Rule::exists('procedure_variants', 'id')->where('procedure_type_id', $this->route('procedureType')->id)],
            'required_procedure_type_id' => ['nullable', 'integer', Rule::notIn([$this->route('procedureType')->id]), 'exists:procedure_types,id'], 'type' => ['required', Rule::enum(PrerequisiteType::class)], 'name' => ['required', 'string', 'max:150'], 'description' => ['nullable', 'string', 'max:2000'], 'conditions_json' => ['nullable', 'json'], 'required' => ['required', 'boolean'], 'sort_order' => ['required', 'integer', 'between:0,65535'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['required' => $this->boolean('required')]);
    }

    public function payload(): array
    {
        $data = $this->validated();
        $data['conditions'] = filled($data['conditions_json'] ?? null) ? json_decode($data['conditions_json'], true, 512, JSON_THROW_ON_ERROR) : [];
        unset($data['conditions_json']);

        return $data;
    }
}
