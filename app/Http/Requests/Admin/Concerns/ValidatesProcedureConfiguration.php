<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Enums\PaymentTiming;
use App\Enums\RequirementType;
use App\Enums\ValidityUnit;
use Illuminate\Validation\Rule;

trait ValidatesProcedureConfiguration
{
    protected function procedureTypeRules(mixed $ignore = null): array
    {
        return [
            'procedure_category_id' => ['nullable', 'integer', 'exists:procedure_categories,id'], 'presentation_modality_id' => ['nullable', 'integer', 'exists:presentation_modalities,id'], 'responsible_area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'code' => ['required', 'string', 'max:30', Rule::unique('procedure_types', 'code')->ignore($ignore)], 'name' => ['required', 'string', 'max:150', Rule::unique('procedure_types', 'name')->ignore($ignore)],
            'description' => ['required', 'string', 'max:2000'], 'instructions' => ['nullable', 'string', 'max:5000'], 'attention_days' => ['required', 'integer', 'between:1,365'],
            'available_from' => ['nullable', 'date'], 'available_until' => ['nullable', 'date', 'after_or_equal:available_from'], 'academic_period' => ['nullable', 'string', 'max:50'], 'unavailable_message' => ['nullable', 'string', 'max:2000'],
            'reception_open' => ['required', 'boolean'], 'allows_digital_registration' => ['required', 'boolean'], 'requires_physical_delivery' => ['required', 'boolean'], 'sort_order' => ['required', 'integer', 'between:0,65535'],
            'physical_submission_location' => ['nullable', 'string', 'max:250'], 'physical_submission_schedule' => ['nullable', 'string', 'max:250'], 'physical_submission_deadline_days' => ['nullable', 'integer', 'between:1,365'],
            'requires_payment' => ['required', 'boolean'], 'amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'required_if:requires_payment,1'], 'currency' => ['required', 'string', 'size:3'], 'payment_concept' => ['nullable', 'string', 'max:200'], 'payment_timing' => ['nullable', Rule::enum(PaymentTiming::class)], 'payment_observation' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareProcedureType(): void
    {
        $this->merge(['code' => mb_strtoupper(trim((string) $this->input('code'))), 'name' => trim((string) $this->input('name')), 'description' => trim((string) $this->input('description')), 'reception_open' => $this->has('reception_open') ? $this->boolean('reception_open') : true, 'allows_digital_registration' => $this->has('allows_digital_registration') ? $this->boolean('allows_digital_registration') : true, 'requires_physical_delivery' => $this->boolean('requires_physical_delivery'), 'requires_payment' => $this->boolean('requires_payment'), 'sort_order' => $this->input('sort_order', 0), 'currency' => mb_strtoupper((string) $this->input('currency', 'PEN'))]);
    }

    protected function requirementRules(mixed $ignore = null): array
    {
        $typeId = $this->route('procedureType')->id;

        return [
            'procedure_variant_id' => ['nullable', 'integer', Rule::exists('procedure_variants', 'id')->where('procedure_type_id', $typeId)],
            'name' => ['required', 'string', 'max:150', Rule::unique('procedure_requirements', 'name')->where('procedure_type_id', $typeId)->ignore($ignore)],
            'description' => ['required', 'string', 'max:2000'], 'type' => ['required', Rule::enum(RequirementType::class)], 'required' => ['required', 'boolean'], 'sort_order' => ['required', 'integer', 'between:0,65535'],
            'requires_original' => ['required', 'boolean'], 'requires_simple_copy' => ['required', 'boolean'], 'requires_authenticated_copy' => ['required', 'boolean'], 'requires_legalized_copy' => ['required', 'boolean'], 'requires_endorsement' => ['required', 'boolean'], 'copy_count' => ['required', 'integer', 'between:1,100'],
            'allowed_formats_text' => ['nullable', 'string', 'max:500'], 'max_file_size_kb' => ['nullable', 'integer', 'between:1,102400'], 'requires_issue_date' => ['required', 'boolean'], 'validity_value' => ['nullable', 'integer', 'between:1,65535'], 'validity_unit' => ['nullable', Rule::enum(ValidityUnit::class), 'required_with:validity_value'],
            'requires_physical_submission' => ['required', 'boolean'], 'requires_digital_file' => ['required', 'boolean'], 'sensitive' => ['required', 'boolean'], 'observations' => ['nullable', 'string', 'max:2000'], 'conditions_json' => ['nullable', 'json'],
        ];
    }

    protected function prepareRequirement(): void
    {
        foreach (['required', 'requires_original', 'requires_simple_copy', 'requires_authenticated_copy', 'requires_legalized_copy', 'requires_endorsement', 'requires_issue_date', 'requires_physical_submission', 'requires_digital_file', 'sensitive'] as $field) {
            $values[$field] = $field === 'requires_digital_file' && ! $this->has($field) ? true : $this->boolean($field);
        }
        $this->merge(['name' => trim((string) $this->input('name')), 'description' => trim((string) $this->input('description')), 'type' => $this->input('type', 'digital_file'), 'sort_order' => $this->input('sort_order', 0), 'copy_count' => $this->input('copy_count', 1), ...$values]);
    }

    public function requirementPayload(): array
    {
        $data = $this->validated();
        $data['allowed_formats'] = collect(explode(',', (string) ($data['allowed_formats_text'] ?? '')))->map(fn ($value) => trim(mb_strtolower($value)))->filter()->unique()->values()->all() ?: null;
        $data['conditions'] = filled($data['conditions_json'] ?? null) ? json_decode($data['conditions_json'], true, 512, JSON_THROW_ON_ERROR) : [];
        unset($data['allowed_formats_text'], $data['conditions_json']);

        return $data;
    }
}
