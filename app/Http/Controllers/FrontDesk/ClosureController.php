<?php

namespace App\Http\Controllers\FrontDesk;

use App\Http\Controllers\Controller;
use App\Http\Requests\FrontDesk\ClosureIndexRequest;
use App\Models\ProcedureRequest;
use App\Services\ProcedureRequestAttentionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClosureController extends Controller
{
    public function index(ClosureIndexRequest $request): View
    {
        $filters = $request->validated();
        $procedureRequests = ProcedureRequest::query()
            ->with(['procedureType', 'status', 'response.author.area'])
            ->whereHas('status', fn ($query) => $query->whereIn('code', ['ATENDIDO', 'FINALIZADO']))
            ->when($filters['codigo'] ?? null, fn ($query, $code) => $query->where('tracking_code', 'like', "%{$code}%"))
            ->latest('updated_at')->paginate(10)->withQueryString();

        return view('front-desk.closures.index', compact('procedureRequests', 'filters'));
    }

    public function show(ProcedureRequest $procedureRequest): View
    {
        Gate::authorize('close', $procedureRequest);
        $procedureRequest->load(['procedureType', 'status', 'user', 'response.author.area', 'latestDerivation.destinationArea']);

        return view('front-desk.closures.show', compact('procedureRequest'));
    }

    public function downloadResponse(ProcedureRequest $procedureRequest): StreamedResponse
    {
        Gate::authorize('reviewResponseDownload', $procedureRequest);
        $response = $procedureRequest->response()->firstOrFail();

        return Storage::disk($response->disk)->download(
            $response->path,
            "{$procedureRequest->tracking_code}-respuesta.{$response->extension}",
            ['Content-Type' => $response->mime_type],
        );
    }

    public function finalize(
        Request $request,
        ProcedureRequest $procedureRequest,
        ProcedureRequestAttentionService $service,
    ): RedirectResponse {
        Gate::authorize('close', $procedureRequest);
        $service->finalize($procedureRequest, $request->user());

        return back()->with('status', 'El expediente fue finalizado correctamente.');
    }
}
