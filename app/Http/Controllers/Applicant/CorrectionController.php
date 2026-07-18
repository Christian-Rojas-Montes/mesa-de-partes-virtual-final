<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Applicant\SubmitCorrectionRequest;
use App\Models\ProcedureRequest;
use App\Models\RequestObservation;
use App\Services\ProcedureRequestTransitionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CorrectionController extends Controller
{
    public function create(ProcedureRequest $procedureRequest): View
    {
        Gate::authorize('correct', $procedureRequest);
        $observation = $procedureRequest->observations()->whereNull('resolved_at')->latest()->firstOrFail();

        return view('applicant.corrections.create', compact('procedureRequest', 'observation'));
    }

    public function store(
        SubmitCorrectionRequest $request,
        ProcedureRequest $procedureRequest,
        ProcedureRequestTransitionService $service,
    ): RedirectResponse {
        $observation = RequestObservation::findOrFail($request->integer('observation_id'));
        $service->submitCorrection(
            $procedureRequest,
            $observation,
            $request->user(),
            $request->file('documents', []),
            $request->validated('message'),
        );

        return to_route('applicant.procedure-requests.show', $procedureRequest)
            ->with('status', 'La subsanación fue registrada y el expediente retornó a revisión.');
    }
}
