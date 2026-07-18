<?php

namespace App\Http\Controllers\AreaManager;

use App\Http\Controllers\Controller;
use App\Http\Requests\AreaManager\AssignmentIndexRequest;
use App\Models\ProcedureRequest;
use App\Models\ProcedureType;
use App\Models\RequestDerivation;
use App\Models\RequestDocument;
use App\Services\RequestDerivationService;
use App\Services\SecureDocumentDownloadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssignmentController extends Controller
{
    public function index(AssignmentIndexRequest $request): View
    {
        $filters = $request->validated();
        $areaId = $request->user()->area_id;
        $procedureRequests = ProcedureRequest::query()
            ->with(['procedureType', 'status', 'latestDerivation.destinationArea'])
            ->whereHas('latestDerivation', fn ($query) => $query->where('to_area_id', $areaId))
            ->when($filters['codigo'] ?? null, fn ($query, $code) => $query->where('tracking_code', 'like', "%{$code}%"))
            ->when($filters['tramite'] ?? null, fn ($query, $type) => $query->where('procedure_type_id', $type))
            ->when($filters['desde'] ?? null, fn ($query, $date) => $query->whereHas('latestDerivation', fn ($query) => $query->whereDate('derived_at', '>=', $date)))
            ->when($filters['hasta'] ?? null, fn ($query, $date) => $query->whereHas('latestDerivation', fn ($query) => $query->whereDate('derived_at', '<=', $date)))
            ->when(($filters['recepcion'] ?? null) === 'pendiente', fn ($query) => $query->whereHas('latestDerivation', fn ($query) => $query->whereNull('received_at')))
            ->when(($filters['recepcion'] ?? null) === 'recibido', fn ($query) => $query->whereHas('latestDerivation', fn ($query) => $query->whereNotNull('received_at')))
            ->latest('submitted_at')->paginate(10)->withQueryString();

        return view('area-manager.assignments.index', [
            'procedureRequests' => $procedureRequests,
            'filters' => $filters,
            'procedureTypes' => ProcedureType::query()->orderBy('name')->get(),
        ]);
    }

    public function show(ProcedureRequest $procedureRequest): View
    {
        Gate::authorize('viewAssigned', $procedureRequest);
        $procedureRequest->load([
            'user', 'procedureType', 'status', 'documents.requirement',
            'latestDerivation.destinationArea',
            'derivations' => fn ($query) => $query->with(['originArea', 'destinationArea', 'responsible'])->oldest('derived_at'),
            'histories' => fn ($query) => $query->with('status')->oldest('created_at'),
            'attentionActions' => fn ($query) => $query->with('author')->oldest('created_at'),
            'response.author',
        ]);

        return view('area-manager.assignments.show', compact('procedureRequest'));
    }

    public function download(ProcedureRequest $procedureRequest, RequestDocument $document, SecureDocumentDownloadService $downloads): StreamedResponse
    {
        abort_unless($document->procedure_request_id === $procedureRequest->id, 404);
        Gate::authorize('download', $document);

        return $downloads->download($document, request()->user());
    }

    public function receive(
        Request $request,
        ProcedureRequest $procedureRequest,
        RequestDerivation $derivation,
        RequestDerivationService $service,
    ): RedirectResponse {
        Gate::authorize('receiveAssigned', $procedureRequest);
        abort_unless($derivation->procedure_request_id === $procedureRequest->id, 404);
        $service->receive($procedureRequest, $derivation, $request->user());

        return back()->with('status', 'La recepción del expediente fue registrada.');
    }
}
