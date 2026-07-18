<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportRequest;
use App\Services\ProcedureRequestQueryService;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(ReportRequest $request, ProcedureRequestQueryService $queries): View
    {
        $filters = $request->validated();
        $report = $queries->report($request->user(), $filters);

        return view('reports.index', compact('filters', 'report'));
    }
}
