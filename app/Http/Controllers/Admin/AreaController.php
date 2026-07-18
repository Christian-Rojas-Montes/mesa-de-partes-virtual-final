<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CatalogIndexRequest;
use App\Http\Requests\Admin\StoreAreaRequest;
use App\Http\Requests\Admin\UpdateAreaRequest;
use App\Models\Area;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AreaController extends Controller
{
    public function index(CatalogIndexRequest $request): View
    {
        Gate::authorize('viewAny', Area::class);
        $search = $request->validated('buscar');
        $areas = Area::query()
            ->when($search, fn ($query) => $query->where(fn ($query) => $query
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")))
            ->orderBy('name')->paginate(10)->withQueryString();

        return view('admin.areas.index', compact('areas', 'search'));
    }

    public function create(): View
    {
        Gate::authorize('create', Area::class);

        return view('admin.areas.create');
    }

    public function store(StoreAreaRequest $request): RedirectResponse
    {
        Area::query()->create($request->validated());

        return to_route('admin.areas.index')->with('status', 'El área fue creada correctamente.');
    }

    public function edit(Area $area): View
    {
        Gate::authorize('update', $area);

        return view('admin.areas.edit', compact('area'));
    }

    public function update(UpdateAreaRequest $request, Area $area): RedirectResponse
    {
        $area->update($request->validated());

        return to_route('admin.areas.index')->with('status', 'El área fue actualizada correctamente.');
    }

    public function toggle(Area $area): RedirectResponse
    {
        Gate::authorize('toggle', $area);
        $area->update(['active' => ! $area->active]);

        return back()->with('status', $area->active ? 'El área fue activada.' : 'El área fue desactivada.');
    }
}
