<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentTiming;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CatalogIndexRequest;
use App\Http\Requests\Admin\StoreProcedureTypeRequest;
use App\Http\Requests\Admin\UpdateProcedureTypeRequest;
use App\Models\Area;
use App\Models\PresentationModality;
use App\Models\ProcedureCategory;
use App\Models\ProcedureType;
use App\Services\Admin\ConfigurableCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProcedureTypeController extends Controller
{
    public function index(CatalogIndexRequest $request): View
    {
        Gate::authorize('viewAny', ProcedureType::class);
        $search = $request->validated('buscar');
        $procedureTypes = ProcedureType::query()->with(['category', 'presentationModality'])->withCount(['requirements', 'variants'])
            ->when($search, fn ($query) => $query->where(fn ($query) => $query->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")))
            ->orderBy('sort_order')->orderBy('name')->paginate(10)->withQueryString();

        return view('admin.procedure-types.index', compact('procedureTypes', 'search'));
    }

    public function create(): View
    {
        Gate::authorize('create', ProcedureType::class);

        return view('admin.procedure-types.create', $this->formData());
    }

    public function store(StoreProcedureTypeRequest $request, ConfigurableCatalogService $service): RedirectResponse
    {
        $service->create(ProcedureType::class, $request->validated());

        return to_route('admin.procedure-types.index')->with('status', 'El trámite fue creado correctamente.');
    }

    public function edit(ProcedureType $procedureType): View
    {
        Gate::authorize('update', $procedureType);

        return view('admin.procedure-types.edit', ['procedureType' => $procedureType, ...$this->formData()]);
    }

    public function update(UpdateProcedureTypeRequest $request, ProcedureType $procedureType, ConfigurableCatalogService $service): RedirectResponse
    {
        $service->update($procedureType, $request->validated());

        return to_route('admin.procedure-types.index')->with('status', 'El trámite fue actualizado correctamente.');
    }

    public function toggle(ProcedureType $procedureType, ConfigurableCatalogService $service): RedirectResponse
    {
        Gate::authorize('toggle', $procedureType);
        $procedureType = $service->toggle($procedureType);

        return back()->with('status', $procedureType->active ? 'El trámite fue activado.' : 'El trámite fue desactivado.');
    }

    private function formData(): array
    {
        return ['categories' => ProcedureCategory::query()->orderBy('sort_order')->orderBy('name')->get(), 'modalities' => PresentationModality::query()->orderBy('name')->get(), 'areas' => Area::query()->active()->orderBy('name')->get(), 'paymentTimings' => PaymentTiming::cases()];
    }
}
