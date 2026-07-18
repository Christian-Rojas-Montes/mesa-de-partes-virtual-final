<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProcedureHistoryRequest;
use App\Models\ProcedureRequest;
use App\Services\ProcedureTimelineService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class ProcedureHistoryController extends Controller
{
    public function show(ProcedureHistoryRequest $request, ProcedureRequest $procedureRequest, ProcedureTimelineService $timeline): View
    {
        $filters = $request->validated();
        $events = $timeline->staff($procedureRequest)->when($filters['tipo'] ?? null, fn ($items, $type) => $items->where('type', $type))->when($filters['desde'] ?? null, fn ($items, $date) => $items->filter(fn ($event) => $event['occurred_at']->toDateString() >= $date))->when($filters['hasta'] ?? null, fn ($items, $date) => $items->filter(fn ($event) => $event['occurred_at']->toDateString() <= $date))->values();
        $page = LengthAwarePaginator::resolveCurrentPage();
        $paginatedEvents = new LengthAwarePaginator($events->forPage($page, 15)->values(), $events->count(), 15, $page, ['path' => $request->url(), 'query' => $request->query()]);
        $procedureRequest->load(['user', 'procedureType', 'variant', 'status']);

        return view('history.staff', compact('procedureRequest', 'paginatedEvents', 'filters'));
    }
}
