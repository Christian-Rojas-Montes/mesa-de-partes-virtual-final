<?php

namespace App\Services;

use App\Enums\ProcedureAvailability;
use App\Models\ProcedureType;
use App\Models\ProcedureVariant;
use Illuminate\Database\Eloquent\Builder;

class PublicProcedureCatalogService
{
    public function __construct(private readonly StructuredConditionEvaluator $conditions) {}

    public function query(array $filters): Builder
    {
        return ProcedureType::query()->where('active', true)
            ->with(['category', 'presentationModality', 'responsibleArea'])
            ->withCount(['variants as active_variants_count' => fn ($query) => $query->where('active', true)])
            ->when($filters['buscar'] ?? null, fn ($query, $value) => $query->where(fn ($query) => $query->where('name', 'like', "%{$value}%")->orWhere('description', 'like', "%{$value}%")))
            ->when($filters['categoria'] ?? null, fn ($query, $value) => $query->whereHas('category', fn ($query) => $query->where('code', $value)))
            ->when($filters['modalidad'] ?? null, fn ($query, $value) => $query->whereHas('presentationModality', fn ($query) => $query->where('code', $value)))
            ->when($filters['publico'] ?? null, fn ($query, $value) => $query->whereHas('category', fn ($query) => $query->where('name', 'like', "%{$value}%")))
            ->orderBy('sort_order')->orderBy('name');
    }

    public function availability(ProcedureType|ProcedureVariant $procedure): ProcedureAvailability
    {
        if (! $procedure->active) {
            return ProcedureAvailability::SUSPENDED;
        }
        if ($procedure->available_from?->isFuture()) {
            return ProcedureAvailability::UPCOMING;
        }
        if (! $procedure->reception_open || $procedure->available_until?->isPast()) {
            return ProcedureAvailability::CLOSED;
        }

        return ProcedureAvailability::AVAILABLE;
    }

    public function canStart(ProcedureType $procedure, ?ProcedureVariant $variant = null): bool
    {
        return $this->availability($procedure) === ProcedureAvailability::AVAILABLE
            && $procedure->allows_digital_registration
            && ($variant === null || ($variant->procedure_type_id === $procedure->id
                && $variant->allows_digital_registration
                && $this->availability($variant) === ProcedureAvailability::AVAILABLE));
    }

    public function selectVariant(ProcedureType $procedure, array $answers, ?string $variantCode = null): array
    {
        $evaluations = $procedure->variants()->where('active', true)->orderBy('sort_order')->get()->mapWithKeys(fn ($variant) => [$variant->id => $this->conditions->evaluate($variant->conditions, $answers)]);
        $variant = $procedure->variants()->where('active', true)->orderBy('sort_order')->get()->first(fn ($variant) => ($variantCode === null || $variant->code === $variantCode) && $evaluations[$variant->id]['eligible']);

        return ['variant' => $variant, 'evaluations' => $evaluations, 'answers' => $answers];
    }

    public function selectorFields(ProcedureType $procedure): array
    {
        return $procedure->variants->flatMap(fn ($variant) => $this->conditions->fields($variant->conditions))->unique()->values()->all();
    }
}
