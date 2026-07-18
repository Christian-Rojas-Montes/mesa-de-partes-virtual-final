<?php

namespace App\Http\Controllers\AreaManager;

use App\Http\Controllers\Controller;
use App\Http\Requests\AreaManager\StoreAttentionActionRequest;
use App\Http\Requests\AreaManager\StoreResponseRequest;
use App\Models\ProcedureRequest;
use App\Services\ProcedureRequestAttentionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttentionController extends Controller
{
    public function start(
        Request $request,
        ProcedureRequest $procedureRequest,
        ProcedureRequestAttentionService $service,
    ): RedirectResponse {
        Gate::authorize('attendAssigned', $procedureRequest);
        $service->start($procedureRequest, $request->user());

        return back()->with('status', 'La atención del expediente fue iniciada.');
    }

    public function storeAction(
        StoreAttentionActionRequest $request,
        ProcedureRequest $procedureRequest,
        ProcedureRequestAttentionService $service,
    ): RedirectResponse {
        $service->recordAction($procedureRequest, $request->user(), $request->validated('description'));

        return back()->with('status', 'La acción de atención fue registrada.');
    }

    public function storeResponse(
        StoreResponseRequest $request,
        ProcedureRequest $procedureRequest,
        ProcedureRequestAttentionService $service,
    ): RedirectResponse {
        $service->respond(
            $procedureRequest,
            $request->user(),
            $request->validated('summary'),
            $request->file('document'),
        );

        return back()->with('status', 'La respuesta final fue registrada.');
    }

    public function downloadResponse(ProcedureRequest $procedureRequest): StreamedResponse
    {
        Gate::authorize('attendAssigned', $procedureRequest);
        $response = $procedureRequest->response()->firstOrFail();

        return Storage::disk($response->disk)->download(
            $response->path,
            "{$procedureRequest->tracking_code}-respuesta.{$response->extension}",
            ['Content-Type' => $response->mime_type],
        );
    }
}
