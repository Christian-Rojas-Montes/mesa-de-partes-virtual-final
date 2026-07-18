<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PresentationModeCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CatalogIndexRequest;
use App\Http\Requests\Admin\SavePresentationModalityRequest;
use App\Models\PresentationModality;
use App\Services\Admin\ConfigurableCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PresentationModalityController extends Controller
{
    public function index(CatalogIndexRequest $request): View
    {
        Gate::authorize('viewAny', PresentationModality::class);
        $search = $request->validated('buscar');
        $items = PresentationModality::query()->withCount('procedureTypes')->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))->orderBy('name')->paginate(10)->withQueryString();

        return view('admin.simple-catalog.index', ['items' => $items, 'search' => $search, 'title' => 'Modalidades', 'description' => 'Configura las modalidades de presentación.', 'routeBase' => 'admin.presentation-modalities', 'countLabel' => 'Trámites']);
    }

    public function create(): View
    {
        Gate::authorize('create', PresentationModality::class);

        return view('admin.simple-catalog.form', ['item' => null, 'title' => 'Crear modalidad', 'routeBase' => 'admin.presentation-modalities', 'codes' => PresentationModeCode::cases(), 'hasOrder' => false]);
    }

    public function store(SavePresentationModalityRequest $request, ConfigurableCatalogService $service): RedirectResponse
    {
        $service->create(PresentationModality::class, $request->validated());

        return to_route('admin.presentation-modalities.index')->with('status', 'La modalidad fue creada.');
    }

    public function edit(PresentationModality $presentationModality): View
    {
        Gate::authorize('update', $presentationModality);

        return view('admin.simple-catalog.form', ['item' => $presentationModality, 'title' => 'Editar modalidad', 'routeBase' => 'admin.presentation-modalities', 'codes' => PresentationModeCode::cases(), 'hasOrder' => false]);
    }

    public function update(SavePresentationModalityRequest $request, PresentationModality $presentationModality, ConfigurableCatalogService $service): RedirectResponse
    {
        $service->update($presentationModality, $request->validated());

        return to_route('admin.presentation-modalities.index')->with('status', 'La modalidad fue actualizada.');
    }

    public function toggle(PresentationModality $presentationModality, ConfigurableCatalogService $service): RedirectResponse
    {
        Gate::authorize('toggle', $presentationModality);
        $service->toggle($presentationModality);

        return back()->with('status', 'El estado de la modalidad fue actualizado.');
    }
}
