<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentTiming;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveProcedureVariantRequest;
use App\Models\PresentationModality;
use App\Models\ProcedureType;
use App\Models\ProcedureVariant;
use App\Services\Admin\ConfigurableCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProcedureVariantController extends Controller
{
    public function index(ProcedureType $procedureType): View
    {
        Gate::authorize('viewAny', ProcedureVariant::class);
        $items = $procedureType->variants()->with('presentationModality')->orderBy('sort_order')->paginate(10);

        return view('admin.nested-catalog.index', ['procedureType' => $procedureType, 'items' => $items, 'kind' => 'variants', 'title' => 'Variantes']);
    }

    public function create(ProcedureType $procedureType): View
    {
        Gate::authorize('create', ProcedureVariant::class);

        return view('admin.nested-catalog.form', ['procedureType' => $procedureType, 'item' => null, 'kind' => 'variants', 'modalities' => PresentationModality::all(), 'paymentTimings' => PaymentTiming::cases()]);
    }

    public function store(SaveProcedureVariantRequest $request, ProcedureType $procedureType, ConfigurableCatalogService $service): RedirectResponse
    {
        $service->create(ProcedureVariant::class, ['procedure_type_id' => $procedureType->id, ...$request->payload()]);

        return to_route('admin.procedure-types.variants.index', $procedureType)->with('status', 'La variante fue creada.');
    }

    public function edit(ProcedureType $procedureType, ProcedureVariant $procedureVariant): View
    {
        $this->belongs($procedureType, $procedureVariant);
        Gate::authorize('update', $procedureVariant);

        return view('admin.nested-catalog.form', ['procedureType' => $procedureType, 'item' => $procedureVariant, 'kind' => 'variants', 'modalities' => PresentationModality::all(), 'paymentTimings' => PaymentTiming::cases()]);
    }

    public function update(SaveProcedureVariantRequest $request, ProcedureType $procedureType, ProcedureVariant $procedureVariant, ConfigurableCatalogService $service): RedirectResponse
    {
        $this->belongs($procedureType, $procedureVariant);
        $service->update($procedureVariant, $request->payload());

        return to_route('admin.procedure-types.variants.index', $procedureType)->with('status', 'La variante fue actualizada.');
    }

    public function toggle(ProcedureType $procedureType, ProcedureVariant $procedureVariant, ConfigurableCatalogService $service): RedirectResponse
    {
        $this->belongs($procedureType, $procedureVariant);
        Gate::authorize('toggle', $procedureVariant);
        $service->toggle($procedureVariant);

        return back()->with('status', 'El estado de la variante fue actualizado.');
    }

    private function belongs(ProcedureType $type, ProcedureVariant $item): void
    {
        abort_unless($item->procedure_type_id === $type->id, 404);
    }
}
