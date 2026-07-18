<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CatalogIndexRequest;
use App\Http\Requests\Admin\SaveProcedureCategoryRequest;
use App\Models\ProcedureCategory;
use App\Services\Admin\ConfigurableCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProcedureCategoryController extends Controller
{
    public function index(CatalogIndexRequest $request): View
    {
        Gate::authorize('viewAny', ProcedureCategory::class);
        $search = $request->validated('buscar');
        $items = ProcedureCategory::query()->withCount('procedureTypes')->when($search, fn ($q) => $q->where(fn ($q) => $q->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")))->orderBy('sort_order')->paginate(10)->withQueryString();

        return view('admin.simple-catalog.index', ['items' => $items, 'search' => $search, 'title' => 'Categorías de trámites', 'description' => 'Organiza los trámites por público o finalidad.', 'routeBase' => 'admin.procedure-categories', 'countLabel' => 'Trámites']);
    }

    public function create(): View
    {
        Gate::authorize('create', ProcedureCategory::class);

        return view('admin.simple-catalog.form', ['item' => null, 'title' => 'Crear categoría', 'routeBase' => 'admin.procedure-categories', 'codes' => null, 'hasOrder' => true]);
    }

    public function store(SaveProcedureCategoryRequest $request, ConfigurableCatalogService $service): RedirectResponse
    {
        $service->create(ProcedureCategory::class, $request->validated());

        return to_route('admin.procedure-categories.index')->with('status', 'La categoría fue creada.');
    }

    public function edit(ProcedureCategory $procedureCategory): View
    {
        Gate::authorize('update', $procedureCategory);

        return view('admin.simple-catalog.form', ['item' => $procedureCategory, 'title' => 'Editar categoría', 'routeBase' => 'admin.procedure-categories', 'codes' => null, 'hasOrder' => true]);
    }

    public function update(SaveProcedureCategoryRequest $request, ProcedureCategory $procedureCategory, ConfigurableCatalogService $service): RedirectResponse
    {
        $service->update($procedureCategory, $request->validated());

        return to_route('admin.procedure-categories.index')->with('status', 'La categoría fue actualizada.');
    }

    public function toggle(ProcedureCategory $procedureCategory, ConfigurableCatalogService $service): RedirectResponse
    {
        Gate::authorize('toggle', $procedureCategory);
        $service->toggle($procedureCategory);

        return back()->with('status', 'El estado de la categoría fue actualizado.');
    }
}
