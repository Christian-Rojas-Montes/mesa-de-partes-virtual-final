<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RequirementType;
use App\Enums\ValidityUnit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProcedureRequirementRequest;
use App\Http\Requests\Admin\UpdateProcedureRequirementRequest;
use App\Models\ProcedureRequirement;
use App\Models\ProcedureType;
use App\Services\Admin\ConfigurableCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProcedureRequirementController extends Controller
{
    public function index(ProcedureType $procedureType): View
    {
        Gate::authorize('viewAny', ProcedureRequirement::class);
        $requirements = $procedureType->requirements()->with('variant')->orderBy('sort_order')->orderBy('name')->paginate(10);

        return view('admin.requirements.index', compact('procedureType', 'requirements'));
    }

    public function create(ProcedureType $procedureType): View
    {
        Gate::authorize('create', ProcedureRequirement::class);

        return view('admin.requirements.create', ['procedureType' => $procedureType, ...$this->formData($procedureType)]);
    }

    public function store(StoreProcedureRequirementRequest $request, ProcedureType $procedureType, ConfigurableCatalogService $service): RedirectResponse
    {
        $service->create(ProcedureRequirement::class, ['procedure_type_id' => $procedureType->id, ...$request->requirementPayload()]);

        return to_route('admin.procedure-types.requirements.index', $procedureType)->with('status', 'El requisito fue creado.');
    }

    public function edit(ProcedureType $procedureType, ProcedureRequirement $requirement): View
    {
        $this->ensureBelongs($procedureType, $requirement);
        Gate::authorize('update', $requirement);

        return view('admin.requirements.edit', ['procedureType' => $procedureType, 'requirement' => $requirement, ...$this->formData($procedureType)]);
    }

    public function update(UpdateProcedureRequirementRequest $request, ProcedureType $procedureType, ProcedureRequirement $requirement, ConfigurableCatalogService $service): RedirectResponse
    {
        $this->ensureBelongs($procedureType, $requirement);
        $service->update($requirement, $request->requirementPayload());

        return to_route('admin.procedure-types.requirements.index', $procedureType)->with('status', 'El requisito fue actualizado.');
    }

    public function toggle(ProcedureType $procedureType, ProcedureRequirement $requirement, ConfigurableCatalogService $service): RedirectResponse
    {
        $this->ensureBelongs($procedureType, $requirement);
        Gate::authorize('toggle', $requirement);
        $service->toggle($requirement);

        return back()->with('status', 'El estado del requisito fue actualizado.');
    }

    private function ensureBelongs(ProcedureType $type, ProcedureRequirement $requirement): void
    {
        abort_unless($requirement->procedure_type_id === $type->id, 404);
    }

    private function formData(ProcedureType $type): array
    {
        return ['variants' => $type->variants()->orderBy('sort_order')->get(), 'requirementTypes' => RequirementType::cases(), 'validityUnits' => ValidityUnit::cases()];
    }
}
