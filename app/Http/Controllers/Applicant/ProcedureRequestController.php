<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Applicant\ProcedureRequestIndexRequest;
use App\Http\Requests\StoreProcedureRequestRequest;
use App\Models\ProcedureRequest;
use App\Models\ProcedureType;
use App\Models\RequestDocument;
use App\Models\Status;
use App\Services\DynamicProcedureFormService;
use App\Services\ProcedureRequestSubmissionService;
use App\Services\ProcedureTimelineService;
use App\Services\SecureDocumentDownloadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProcedureRequestController extends Controller
{
    public function index(ProcedureRequestIndexRequest $request): View
    {
        $filters = $request->validated();
        $procedureRequests = $request->user()->procedureRequests()
            ->with(['procedureType', 'status'])->withCount('documents')
            ->when($filters['codigo'] ?? null, fn ($query, $code) => $query->where('tracking_code', 'like', "%{$code}%"))
            ->when($filters['estado'] ?? null, fn ($query, $status) => $query->where('status_id', $status))
            ->when($filters['tramite'] ?? null, fn ($query, $type) => $query->where('procedure_type_id', $type))
            ->when($filters['desde'] ?? null, fn ($query, $date) => $query->whereDate('submitted_at', '>=', $date))
            ->when($filters['hasta'] ?? null, fn ($query, $date) => $query->whereDate('submitted_at', '<=', $date))
            ->latest('submitted_at')->paginate(10)->withQueryString();

        return view('applicant.procedure-requests.index', [
            'procedureRequests' => $procedureRequests,
            'filters' => $filters,
            'statuses' => Status::query()->orderBy('sort_order')->get(),
            'procedureTypes' => ProcedureType::query()->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request, DynamicProcedureFormService $forms): View
    {
        Gate::authorize('create', ProcedureRequest::class);
        $procedureTypes = ProcedureType::active()->with('category')->orderBy('name')->get();
        $selectedType = null;
        $selectedVariant = null;
        $fields = collect();
        $requirements = collect();
        $eligibility = [];
        if ($request->integer('tipo')) {
            $selectedType = $forms->loadType($request->integer('tipo'));
            $eligibility = (array) data_get($request->session()->get("catalog.selection.{$selectedType->id}"), 'answers', []);
            $variantCode = $request->string('variante')->toString();
            $selectedVariant = $variantCode !== '' ? $selectedType->variants->firstWhere('code', $variantCode) : null;
            if ($selectedType->variants->isEmpty() || $selectedVariant) {
                if ($selectedVariant) {
                    $selectedVariant = $forms->variant($selectedType, $selectedVariant->id, $eligibility);
                }
                $fields = $forms->fields($selectedType, $selectedVariant, old('responses', []));
                $requirements = $forms->requirements($selectedType, $selectedVariant, old('responses', []));
            }
        }

        return view('applicant.procedure-requests.create', compact('procedureTypes', 'selectedType', 'selectedVariant', 'fields', 'requirements', 'eligibility', 'forms'));
    }

    public function store(
        StoreProcedureRequestRequest $request,
        ProcedureRequestSubmissionService $submissionService,
    ): RedirectResponse {
        $context = $request->submissionContext();
        $procedureRequest = $submissionService->submit(
            $request->user(),
            $request->safe()->except(['documents', 'confirmation', 'responses', 'eligibility']),
            $request->file('documents', []),
            $context,
        );

        return to_route('applicant.procedure-requests.show', $procedureRequest)
            ->with('status', "Solicitud registrada con el código {$procedureRequest->tracking_code}.");
    }

    public function show(ProcedureRequest $procedureRequest, ProcedureTimelineService $timeline): View
    {
        Gate::authorize('view', $procedureRequest);
        $procedureRequest->load([
            'procedureType',
            'variant',
            'titleProcess',
            'status',
            'documents.requirement',
            'histories' => fn ($query) => $query->with('status')->oldest('created_at'),
            'observations' => fn ($query) => $query->latest('created_at'),
            'corrections.documents',
            'derivations' => fn ($query) => $query->with(['originArea', 'destinationArea'])->oldest('derived_at'),
            'response.author.area',
            'physicalReception.receivingArea',
            'rejection',
        ]);
        $timelineEvents = $timeline->applicant($procedureRequest);
        $relatedNotifications = auth()->user()->notifications()
            ->where('data->procedure_request_id', $procedureRequest->id)
            ->latest()->get();

        return view('applicant.procedure-requests.show', compact('procedureRequest', 'relatedNotifications', 'timelineEvents'));
    }

    public function download(ProcedureRequest $procedureRequest, RequestDocument $document, SecureDocumentDownloadService $downloads): StreamedResponse
    {
        abort_unless($document->procedure_request_id === $procedureRequest->id, 404);
        Gate::authorize('download', $document);

        return $downloads->download($document, request()->user());
    }

    public function downloadResponse(ProcedureRequest $procedureRequest): StreamedResponse
    {
        Gate::authorize('downloadResponse', $procedureRequest);
        $response = $procedureRequest->response()->firstOrFail();

        return Storage::disk($response->disk)->download(
            $response->path,
            "{$procedureRequest->tracking_code}-respuesta.{$response->extension}",
            ['Content-Type' => $response->mime_type],
        );
    }
}
