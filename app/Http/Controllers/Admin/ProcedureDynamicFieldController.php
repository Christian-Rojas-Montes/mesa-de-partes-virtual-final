<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DynamicFieldType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveProcedureDynamicFieldRequest;
use App\Models\ProcedureDynamicField;
use App\Models\ProcedureType;
use App\Services\Admin\ConfigurableCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProcedureDynamicFieldController extends Controller
{
    public function index(ProcedureType $procedureType): View
    {
        Gate::authorize('viewAny', ProcedureDynamicField::class);
        $items = $procedureType->dynamicFields()->with('variant')->orderBy('sort_order')->paginate(10);

        return view('admin.nested-catalog.index', ['procedureType' => $procedureType, 'items' => $items, 'kind' => 'dynamic-fields', 'title' => 'Campos dinámicos']);
    }

    public function create(ProcedureType $procedureType): View
    {
        Gate::authorize('create', ProcedureDynamicField::class);

        return view('admin.nested-catalog.form', ['procedureType' => $procedureType, 'item' => null, 'kind' => 'dynamic-fields', 'fieldTypes' => DynamicFieldType::cases(), 'variants' => $procedureType->variants]);
    }

    public function store(SaveProcedureDynamicFieldRequest $request, ProcedureType $procedureType, ConfigurableCatalogService $service): RedirectResponse
    {
        $service->create(ProcedureDynamicField::class, ['procedure_type_id' => $procedureType->id, ...$request->payload()]);

        return to_route('admin.procedure-types.dynamic-fields.index', $procedureType)->with('status', 'El campo fue creado.');
    }

    public function edit(ProcedureType $procedureType, ProcedureDynamicField $dynamicField): View
    {
        $this->belongs($procedureType, $dynamicField);
        Gate::authorize('update', $dynamicField);

        return view('admin.nested-catalog.form', ['procedureType' => $procedureType, 'item' => $dynamicField, 'kind' => 'dynamic-fields', 'fieldTypes' => DynamicFieldType::cases(), 'variants' => $procedureType->variants]);
    }

    public function update(SaveProcedureDynamicFieldRequest $request, ProcedureType $procedureType, ProcedureDynamicField $dynamicField, ConfigurableCatalogService $service): RedirectResponse
    {
        $this->belongs($procedureType, $dynamicField);
        $service->update($dynamicField, $request->payload());

        return to_route('admin.procedure-types.dynamic-fields.index', $procedureType)->with('status', 'El campo fue actualizado.');
    }

    public function toggle(ProcedureType $procedureType, ProcedureDynamicField $dynamicField, ConfigurableCatalogService $service): RedirectResponse
    {
        $this->belongs($procedureType, $dynamicField);
        Gate::authorize('toggle', $dynamicField);
        $service->toggle($dynamicField);

        return back()->with('status', 'El estado del campo fue actualizado.');
    }

    private function belongs(ProcedureType $type, ProcedureDynamicField $item): void
    {
        abort_unless($item->procedure_type_id === $type->id, 404);
    }
}
