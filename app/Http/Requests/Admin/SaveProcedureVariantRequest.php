<?php

namespace App\Http\Requests\Admin;

use App\Enums\PaymentTiming;
use App\Models\ProcedureVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProcedureVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        $model = $this->route('procedureVariant');

        return $this->user()?->can($model ? 'update' : 'create', $model ?: ProcedureVariant::class) === true;
    }

    public function rules(): array
    {
        $type = $this->route('procedureType');

        return [
            'presentation_modality_id' => ['nullable', 'integer', Rule::exists('presentation_modalities', 'id')->where('active', true)],
            'code' => ['required', 'string', 'max:30', Rule::unique('procedure_variants', 'code')->where('procedure_type_id', $type->id)->ignore($this->route('procedureVariant'))],
            'name' => ['required', 'string', 'max:150', Rule::unique('procedure_variants', 'name')->where('procedure_type_id', $type->id)->ignore($this->route('procedureVariant'))],
            'description' => ['nullable', 'string', 'max:2000'], 'conditions_json' => ['nullable', 'json'],
            'available_from' => ['nullable', 'date'], 'available_until' => ['nullable', 'date', 'after_or_equal:available_from'], 'academic_period' => ['nullable', 'string', 'max:50'], 'unavailable_message' => ['nullable', 'string', 'max:2000'],
            'reception_open' => ['required', 'boolean'], 'allows_digital_registration' => ['required', 'boolean'], 'requires_physical_delivery' => ['required', 'boolean'],
            'physical_submission_location' => ['nullable', 'string', 'max:250'], 'physical_submission_schedule' => ['nullable', 'string', 'max:250'], 'physical_submission_deadline_days' => ['nullable', 'integer', 'between:1,365'],
            'attention_days' => ['nullable', 'integer', 'between:1,365'], 'sort_order' => ['required', 'integer', 'between:0,65535'],
            'requires_payment' => ['required', 'boolean'], 'amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'required_if:requires_payment,1'], 'currency' => ['required', 'string', 'size:3'], 'payment_concept' => ['nullable', 'string', 'max:200'], 'payment_timing' => ['nullable', Rule::enum(PaymentTiming::class)], 'payment_observation' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => mb_strtoupper(trim((string) $this->input('code'))), 'reception_open' => $this->boolean('reception_open'), 'allows_digital_registration' => $this->boolean('allows_digital_registration'), 'requires_physical_delivery' => $this->boolean('requires_physical_delivery'), 'requires_payment' => $this->boolean('requires_payment'), 'currency' => mb_strtoupper((string) $this->input('currency', 'PEN'))]);
    }

    public function payload(): array
    {
        $data = $this->validated();
        $data['conditions'] = filled($data['conditions_json'] ?? null) ? json_decode($data['conditions_json'], true, 512, JSON_THROW_ON_ERROR) : [];
        unset($data['conditions_json']);

        return $data;
    }
}
