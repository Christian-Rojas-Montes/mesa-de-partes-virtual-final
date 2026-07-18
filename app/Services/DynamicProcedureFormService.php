<?php

namespace App\Services;

use App\Enums\DynamicFieldType;
use App\Models\ProcedureDynamicField;
use App\Models\ProcedureRequirement;
use App\Models\ProcedureType;
use App\Models\ProcedureVariant;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class DynamicProcedureFormService
{
    public const MAX_FILES = 5;

    public const MAX_FILE_KB = 5120;

    private const ALLOWED_CONFIGURED_RULES = ['string', 'email', 'numeric', 'integer', 'date', 'boolean', 'array', 'min', 'max', 'between', 'in', 'not_in'];

    public function __construct(private readonly PublicProcedureCatalogService $catalog, private readonly StructuredConditionEvaluator $conditions) {}

    public function loadType(int $id): ProcedureType
    {
        $type = ProcedureType::active()->with(['category', 'presentationModality', 'variants' => fn ($q) => $q->where('active', true)->orderBy('sort_order')])->findOrFail($id);
        if (! $this->catalog->canStart($type)) {
            throw ValidationException::withMessages(['procedure_type_id' => $type->unavailable_message ?: 'El trámite no está disponible para registro digital.']);
        }

        return $type;
    }

    public function variant(ProcedureType $type, ?int $variantId, array $eligibility = []): ?ProcedureVariant
    {
        if ($type->variants->isEmpty()) {
            if ($variantId !== null) {
                throw ValidationException::withMessages(['procedure_variant_id' => 'La variante seleccionada no pertenece al trámite.']);
            }

            return null;
        }
        $variant = $type->variants->firstWhere('id', $variantId);
        if (! $variant || ! $this->catalog->canStart($type, $variant)) {
            throw ValidationException::withMessages(['procedure_variant_id' => 'La variante seleccionada no es válida o no está disponible.']);
        }
        $evaluation = $this->conditions->evaluate($variant->conditions ?? [], $eligibility);
        if (! $evaluation['eligible']) {
            throw ValidationException::withMessages(['eligibility' => $evaluation['unmet']]);
        }

        return $variant;
    }

    public function fields(ProcedureType $type, ?ProcedureVariant $variant, array $answers = []): Collection
    {
        return ProcedureDynamicField::query()->where('procedure_type_id', $type->id)->where('active', true)
            ->where(fn ($q) => $q->whereNull('procedure_variant_id')->when($variant, fn ($q) => $q->orWhere('procedure_variant_id', $variant->id)))
            ->orderBy('sort_order')->get()->filter(fn ($field) => $this->conditions->evaluate($field->visibility_conditions ?? [], $answers)['eligible'])->values();
    }

    public function requirements(ProcedureType $type, ?ProcedureVariant $variant, array $answers = []): Collection
    {
        return ProcedureRequirement::query()->where('procedure_type_id', $type->id)->where('active', true)
            ->where(fn ($q) => $q->whereNull('procedure_variant_id')->when($variant, fn ($q) => $q->orWhere('procedure_variant_id', $variant->id)))
            ->orderBy('sort_order')->get()->filter(fn ($requirement) => $this->conditions->evaluate($requirement->conditions ?? [], $answers)['eligible'])->values();
    }

    public function fieldRules(ProcedureDynamicField $field): array
    {
        $rules = [$field->required ? 'required' : 'nullable'];
        $rules[] = match ($field->type) {
            DynamicFieldType::EMAIL => 'email', DynamicFieldType::NUMBER => 'numeric', DynamicFieldType::DATE => 'date',
            DynamicFieldType::YEAR => 'integer', DynamicFieldType::CHECKBOX => 'boolean', DynamicFieldType::MULTISELECT, DynamicFieldType::COURSE_UNITS => 'array', default => 'string',
        };
        if ($field->min_length) {
            $rules[] = 'min:'.$field->min_length;
        }
        if ($field->max_length) {
            $rules[] = 'max:'.$field->max_length;
        }
        if ($field->min_value !== null) {
            $rules[] = 'min:'.$field->min_value;
        }
        if ($field->max_value !== null) {
            $rules[] = 'max:'.$field->max_value;
        }
        if ($field->options && ! in_array($field->type, [DynamicFieldType::MULTISELECT, DynamicFieldType::COURSE_UNITS], true)) {
            $rules[] = 'in:'.implode(',', array_keys($this->options($field)));
        }

        foreach (explode('|', (string) $field->validation_rule) as $configuredRule) {
            $configuredRule = trim($configuredRule);
            $name = str($configuredRule)->before(':')->toString();
            if ($configuredRule !== '' && in_array($name, self::ALLOWED_CONFIGURED_RULES, true) && preg_match('/^[a-z_]+(?::[a-zA-Z0-9_.@, -]+)?$/', $configuredRule)) {
                $rules[] = $configuredRule;
            }
        }

        return array_values(array_unique($rules));
    }

    public function options(ProcedureDynamicField $field): array
    {
        return collect($field->options ?? [])->mapWithKeys(fn ($value, $key) => is_int($key) ? [(string) $value => (string) $value] : [(string) $key => (string) $value])->all();
    }

    public function snapshot(ProcedureType $type, ?ProcedureVariant $variant, Collection $fields, Collection $requirements, array $responses): array
    {
        $source = $variant ?? $type;

        return [
            'procedure' => ['code' => $type->code, 'name' => $type->name],
            'variant' => $variant ? ['code' => $variant->code, 'name' => $variant->name] : null,
            'requirements' => $requirements->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'required' => $r->required, 'digital' => $r->requires_digital_file, 'physical' => $r->requires_physical_submission, 'original' => $r->requires_original, 'copies' => $r->copy_count, 'validity' => $r->validity_value ? $r->validity_value.' '.($r->validity_unit?->value ?? '') : null])->values()->all(),
            'fields' => $fields->map(fn ($f) => ['key' => $f->key, 'label' => $f->label, 'response' => $responses[$f->key] ?? null])->values()->all(),
            'amount' => $source->requires_payment ? ['currency' => $source->currency, 'value' => $source->amount] : null,
            'modality' => $variant?->presentationModality?->name ?? $type->presentationModality?->name,
            'modality_code' => ($variant?->presentationModality?->code ?? $type->presentationModality?->code)?->value,
            'physical_submission' => [
                'required' => $source->requires_physical_delivery || $requirements->contains('requires_physical_submission', true),
                'location' => $source->physical_submission_location ?? $type->physical_submission_location,
                'schedule' => $source->physical_submission_schedule ?? $type->physical_submission_schedule,
                'deadline' => ($source->physical_submission_deadline_days ?? $type->physical_submission_deadline_days) ? now()->addDays($source->physical_submission_deadline_days ?? $type->physical_submission_deadline_days)->toIso8601String() : null,
                'pending_originals' => $requirements->where('requires_physical_submission', true)->map(fn ($requirement) => ['name' => $requirement->name, 'original' => $requirement->requires_original, 'copies' => $requirement->copy_count])->values()->all(),
            ],
            'instructions' => $type->instructions,
            'continuation_department' => $type->continuation_department,
            'captured_at' => now()->toIso8601String(),
        ];
    }
}
