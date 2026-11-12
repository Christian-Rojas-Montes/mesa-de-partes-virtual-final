<?php

namespace App\Http\Controllers\FrontDesk;

use App\Http\Controllers\Controller;
use App\Http\Requests\FrontDesk\DerivationIndexRequest;
use App\Http\Requests\FrontDesk\StoreDerivationRequest;
use App\Models\Area;
use App\Models\ProcedureRequest;
use App\Models\ProcedureType;
use App\Services\RequestDerivationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DerivationController extends Controller
{
    public function index(DerivationIndexRequest $request): View
    {
        $filters = $request->validated();
        $procedureRequests = ProcedureRequest::query()
            ->with(['procedureType', 'status', 'latestDerivation.destinationArea'])
            ->where(function ($query) {
                $query->where(function ($query) {
                    $query->whereNotNull('validated_at')
                        ->whereHas('status', fn ($query) => $query->where('code', 'EN_REVISION'));
                })->orWhere(function ($query) {
                    $query->whereHas('status', fn ($query) => $query->where('code', 'DERIVADO'))
                        ->whereHas('latestDerivation', fn ($query) => $query->whereNull('received_at'));
                });
            })
            ->when($filters['codigo'] ?? null, fn ($query, $code) => $query->where('tracking_code', 'like', "%{$code}%"))
            ->when($filters['tramite'] ?? null, fn ($query, $type) => $query->where('procedure_type_id', $type))
            ->when($filters['area'] ?? null, fn ($query, $area) => $query->whereHas('latestDerivation', fn ($query) => $query->where('to_area_id', $area)))
            ->latest('submitted_at')->paginate(10)->withQueryString();

        return view('front-desk.derivations.index', [
            'procedureRequests' => $procedureRequests,
            'filters' => $filters,
            'procedureTypes' => ProcedureType::query()->orderBy('name')->get(),
            'areas' => Area::query()->orderBy('name')->get(),
        ]);
    }

    public function create(ProcedureRequest $procedureRequest): View
    {
        Gate::authorize('derive', $procedureRequest);
        $procedureRequest->load([
            'procedureType.responsibleArea', 'status', 'user', 'latestDerivation.destinationArea',
            'derivations' => fn ($query) => $query->with(['originArea', 'destinationArea', 'responsible'])->oldest('derived_at'),
        ]);
        $configuredArea = $procedureRequest->procedureType->responsibleArea;
        $latestDerivation = $procedureRequest->latestDerivation;
        $configuredAreaUnavailable = $configuredArea !== null && ! $configuredArea->active;
        $alreadyAtConfiguredArea = $configuredArea?->active
            && $latestDerivation?->to_area_id === $configuredArea->id;
        $correctionBlockedByReceipt = $latestDerivation?->received_at !== null;

        return view('front-desk.derivations.create', [
            'procedureRequest' => $procedureRequest,
            'areas' => $configuredArea?->active
                ? collect([$configuredArea])
                : Area::active()->orderBy('name')->get(),
            'configuredArea' => $configuredArea,
            'configuredAreaUnavailable' => $configuredAreaUnavailable,
            'alreadyAtConfiguredArea' => $alreadyAtConfiguredArea,
            'correctionBlockedByReceipt' => $correctionBlockedByReceipt,
        ]);
    }

    public function store(
        StoreDerivationRequest $request,
        ProcedureRequest $procedureRequest,
        RequestDerivationService $service,
    ): RedirectResponse {
        $data = $request->validated();
        $service->derive(
            $procedureRequest,
            Area::query()->findOrFail($data['area_id']),
            $request->user(),
            $data['reason'] ?? null,
        );

        return redirect()->route('front-desk.derivations.create', $procedureRequest)
            ->with('status', 'El expediente fue derivado correctamente.');
    }
}
