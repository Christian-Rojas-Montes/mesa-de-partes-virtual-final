<?php

namespace App\Http\Controllers\FrontDesk;

use App\Http\Controllers\Controller;
use App\Http\Requests\FrontDesk\AssignAcademicFileNumberRequest;
use App\Http\Requests\FrontDesk\ConfirmPhysicalReceptionRequest;
use App\Http\Requests\FrontDesk\ObserveProcedureRequestRequest;
use App\Http\Requests\FrontDesk\RejectProcedureRequestRequest;
use App\Http\Requests\FrontDesk\ReviewIndexRequest;
use App\Models\ProcedureRequest;
use App\Models\ProcedureType;
use App\Models\RequestDocument;
use App\Models\Status;
use App\Services\ConvalidationTrackingService;
use App\Services\PhysicalReceptionService;
use App\Services\ProcedureRequestTransitionService;
use App\Services\SecureDocumentDownloadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReviewController extends Controller
{
    public function index(ReviewIndexRequest $request): View
    {
        $filters = $request->validated();
        $procedureRequests = ProcedureRequest::query()
            ->with(['user', 'procedureType', 'status'])->withCount('documents')
            ->whereHas('status', fn ($query) => $query->whereIn('code', ['REGISTRADO', 'EN_REVISION']))
            ->when($filters['codigo'] ?? null, fn ($query, $code) => $query->where('tracking_code', 'like', "%{$code}%"))
            ->when($filters['fecha'] ?? null, fn ($query, $date) => $query->whereDate('submitted_at', $date))
            ->when($filters['tramite'] ?? null, fn ($query, $type) => $query->where('procedure_type_id', $type))
            ->when($filters['estado'] ?? null, fn ($query, $status) => $query->where('status_id', $status))
            ->oldest('submitted_at')->paginate(10)->withQueryString();

        return view('front-desk.reviews.index', [
            'procedureRequests' => $procedureRequests,
            'filters' => $filters,
            'procedureTypes' => ProcedureType::query()->orderBy('name')->get(),
            'statuses' => Status::query()->whereIn('code', ['REGISTRADO', 'EN_REVISION'])->orderBy('sort_order')->get(),
        ]);
    }

    public function show(ProcedureRequest $procedureRequest): View
    {
        Gate::authorize('review', $procedureRequest);
        $procedureRequest->load([
            'user',
            'procedureType.requirements', 'procedureType.category',
            'status',
            'documents.requirement',
            'histories' => fn ($query) => $query->with('status')->oldest('created_at'),
            'observations' => fn ($query) => $query->latest('created_at'),
            'rejection',
        ]);

        return view('front-desk.reviews.show', compact('procedureRequest'));
    }

    public function download(ProcedureRequest $procedureRequest, RequestDocument $document, SecureDocumentDownloadService $downloads): StreamedResponse
    {
        abort_unless($document->procedure_request_id === $procedureRequest->id, 404);
        Gate::authorize('download', $document);

        return $downloads->download($document, request()->user());
    }

    public function start(Request $request, ProcedureRequest $procedureRequest, ProcedureRequestTransitionService $service): RedirectResponse
    {
        Gate::authorize('startReview', $procedureRequest);
        $service->startReview($procedureRequest, $request->user());

        return back()->with('status', 'La revisión fue iniciada.');
    }

    public function validateRequest(Request $request, ProcedureRequest $procedureRequest, ProcedureRequestTransitionService $service): RedirectResponse
    {
        Gate::authorize('validateReview', $procedureRequest);
        $service->validate($procedureRequest, $request->user());

        return back()->with('status', 'La solicitud fue validada.');
    }

    public function confirmPhysicalReception(ConfirmPhysicalReceptionRequest $request, ProcedureRequest $procedureRequest, PhysicalReceptionService $service): RedirectResponse
    {
        $service->confirm($procedureRequest, $request->user(), $request->validated());

        return back()->with('status', 'La recepción física fue confirmada.');
    }

    public function assignAcademicFileNumber(AssignAcademicFileNumberRequest $request, ProcedureRequest $procedureRequest, ConvalidationTrackingService $service): RedirectResponse
    {
        $service->assignAcademicFileNumber($procedureRequest, $request->user(), $request->validated('academic_file_number'));

        return back()->with('status', 'El número de expediente académico o externo fue registrado.');
    }

    public function observe(ObserveProcedureRequestRequest $request, ProcedureRequest $procedureRequest, ProcedureRequestTransitionService $service): RedirectResponse
    {
        $service->observe($procedureRequest, $request->user(), $request->validated());

        return back()->with('status', 'La observación fue registrada.');
    }

    public function reject(RejectProcedureRequestRequest $request, ProcedureRequest $procedureRequest, ProcedureRequestTransitionService $service): RedirectResponse
    {
        $service->reject($procedureRequest, $request->user(), $request->validated('reason'));

        return back()->with('status', 'El rechazo fue registrado.');
    }
}
