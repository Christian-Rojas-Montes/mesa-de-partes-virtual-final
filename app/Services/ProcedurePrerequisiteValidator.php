<?php

namespace App\Services;

use App\Enums\PrerequisiteType;
use App\Models\ProcedureType;
use App\Models\User;
use Illuminate\Support\Collection;

class ProcedurePrerequisiteValidator
{
    public function __construct(private readonly StructuredConditionEvaluator $conditions) {}

    /** @return Collection<int, string> */
    public function errors(User $user, ProcedureType $type, array $responses): Collection
    {
        return $type->prerequisites()->where('active', true)->where('required', true)->get()
            ->filter(fn ($item) => $this->conditions->evaluate($item->conditions ?? [], $responses)['eligible'])
            ->filter(fn ($item) => $item->type === PrerequisiteType::APPROVED_PROCEDURE)
            ->filter(fn ($item) => ! $user->procedureRequests()->where('procedure_type_id', $item->required_procedure_type_id)
                ->whereHas('status', fn ($query) => $query->whereIn('code', ['ATENDIDO', 'FINALIZADO']))->exists())
            ->map(fn ($item) => "Debes completar previamente: {$item->name}.")
            ->values();
    }
}
