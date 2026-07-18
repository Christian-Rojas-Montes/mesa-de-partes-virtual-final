<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PrerequisiteType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveProcedurePrerequisiteRequest;
use App\Models\ProcedurePrerequisite;
use App\Models\ProcedureType;
use App\Services\Admin\ConfigurableCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProcedurePrerequisiteController extends Controller
{
    public function index(ProcedureType $procedureType): View
    {
        Gate::authorize('viewAny', ProcedurePrerequisite::class);
        $items = $procedureType->prerequisites()->with(['variant', 'requiredProcedureType'])->orderBy('sort_order')->paginate(10);

        return view('admin.nested-catalog.index', ['procedureType' => $procedureType, 'items' => $items, 'kind' => 'prerequisites', 'title' => 'Prerrequisitos']);
    }

    public function create(ProcedureType $procedureType): View
    {
        Gate::authorize('create', ProcedurePrerequisite::class);

        return view('admin.nested-catalog.form', ['procedureType' => $procedureType, 'item' => null, 'kind' => 'prerequisites', ...$this->data($procedureType)]);
    }

    public function store(SaveProcedurePrerequisiteRequest $request, ProcedureType $procedureType, ConfigurableCatalogService $service): RedirectResponse
    {
        $service->create(ProcedurePrerequisite::class, ['procedure_type_id' => $procedureType->id, ...$request->payload()]);

        return to_route('admin.procedure-types.prerequisites.index', $procedureType)->with('status', 'El prerrequisito fue creado.');
    }

    public function edit(ProcedureType $procedureType, ProcedurePrerequisite $prerequisite): View
    {
        $this->belongs($procedureType, $prerequisite);
        Gate::authorize('update', $prerequisite);

        return view('admin.nested-catalog.form', ['procedureType' => $procedureType, 'item' => $prerequisite, 'kind' => 'prerequisites', ...$this->data($procedureType)]);
    }

    public function update(SaveProcedurePrerequisiteRequest $request, ProcedureType $procedureType, ProcedurePrerequisite $prerequisite, ConfigurableCatalogService $service): RedirectResponse
    {
        $this->belongs($procedureType, $prerequisite);
        $service->update($prerequisite, $request->payload());

        return to_route('admin.procedure-types.prerequisites.index', $procedureType)->with('status', 'El prerrequisito fue actualizado.');
    }

    public function toggle(ProcedureType $procedureType, ProcedurePrerequisite $prerequisite, ConfigurableCatalogService $service): RedirectResponse
    {
        $this->belongs($procedureType, $prerequisite);
        Gate::authorize('toggle', $prerequisite);
        $service->toggle($prerequisite);

        return back()->with('status', 'El estado del prerrequisito fue actualizado.');
    }

    private function data(ProcedureType $type): array
    {
        return ['prerequisiteTypes' => PrerequisiteType::cases(), 'variants' => $type->variants, 'procedureTypes' => ProcedureType::query()->whereKeyNot($type->id)->orderBy('name')->get()];
    }

    private function belongs(ProcedureType $type, ProcedurePrerequisite $item): void
    {
        abort_unless($item->procedure_type_id === $type->id, 404);
    }
}
