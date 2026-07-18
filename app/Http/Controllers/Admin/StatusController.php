<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CatalogIndexRequest;
use App\Models\Status;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class StatusController extends Controller
{
    public function index(CatalogIndexRequest $request): View
    {
        Gate::authorize('viewAny', Status::class);
        $search = $request->validated('buscar');
        $statuses = Status::query()
            ->when($search, fn ($query) => $query->where(fn ($query) => $query
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")))
            ->orderBy('sort_order')->paginate(10)->withQueryString();

        return view('admin.statuses.index', compact('statuses', 'search'));
    }
}
