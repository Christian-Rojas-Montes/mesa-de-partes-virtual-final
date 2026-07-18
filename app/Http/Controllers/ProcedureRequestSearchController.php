<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProcedureRequestSearchRequest;
use App\Models\Area;
use App\Models\AuditLog;
use App\Models\PresentationModality;
use App\Models\ProcedureCategory;
use App\Models\ProcedureRequest;
use App\Models\ProcedureType;
use App\Models\ProcedureVariant;
use App\Models\Status;
use App\Models\User;
use App\Services\ProcedureRequestQueryService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProcedureRequestSearchController extends Controller
{
    public function index(ProcedureRequestSearchRequest $request, ProcedureRequestQueryService $queries): View
    {
        $filters = $request->validated();
        $procedureRequests = $queries->filtered($request->user(), $filters)
            ->with(['user', 'procedureType.category', 'procedureType.presentationModality', 'variant.presentationModality', 'status', 'latestDerivation.destinationArea', 'titleProcess.responsible', 'appointments', 'pickup', 'physicalReception'])
            ->latest('submitted_at')->paginate(15)->withQueryString();
        $procedureRequests->getCollection()->each(function (ProcedureRequest $item) {
            $document = (string) $item->user->document_number;
            $item->user->setAttribute('document_number', str_repeat('*', max(0, strlen($document) - 4)).substr($document, -4));
        });

        return view('search.index', [
            'procedureRequests' => $procedureRequests,
            'filters' => $filters,
            'statuses' => Status::query()->orderBy('sort_order')->get(),
            'procedureTypes' => ProcedureType::query()->orderBy('name')->get(),
            'areas' => Area::query()->orderBy('name')->get(),
            'categories' => ProcedureCategory::query()->orderBy('name')->get(),
            'variants' => ProcedureVariant::query()->orderBy('name')->get(),
            'modalities' => PresentationModality::query()->orderBy('name')->get(),
        ]);
    }

    public function export(ProcedureRequestSearchRequest $request, ProcedureRequestQueryService $queries): StreamedResponse
    {
        $filters = $request->validated();
        AuditLog::create(['user_id' => $request->user()->id, 'action' => 'procedure_search_exported', 'auditable_type' => User::class, 'auditable_id' => $request->user()->id, 'details' => ['filters' => array_keys(array_filter($filters))], 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);

        return response()->streamDownload(function () use ($queries, $request, $filters) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Código', 'Solicitante', 'Documento', 'Carrera', 'Categoría', 'Trámite', 'Variante', 'Modalidad', 'Estado', 'Área', 'Fecha']);
            $queries->filtered($request->user(), $filters)->with(['user', 'procedureType.category', 'procedureType.presentationModality', 'variant.presentationModality', 'status', 'latestDerivation.destinationArea'])->orderBy('id')->chunkById(250, function ($items) use ($output) {
                foreach ($items as $item) {
                    $document = (string) $item->user->document_number;
                    fputcsv($output, [$item->tracking_code, trim($item->user->first_name.' '.$item->user->last_name), str_repeat('*', max(0, strlen($document) - 4)).substr($document, -4), $item->user->academic_program, $item->procedureType->category?->name, $item->procedureType->name, $item->variant?->name, $item->variant?->presentationModality?->name ?? $item->procedureType->presentationModality?->name, $item->status->name, $item->latestDerivation?->destinationArea?->name, $item->submitted_at?->format('Y-m-d')]);
                }
            });
            fclose($output);
        }, 'expedientes-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store, no-cache, max-age=0', 'X-Content-Type-Options' => 'nosniff', 'X-Robots-Tag' => 'noindex, nofollow, noarchive']);
    }

    public function show(ProcedureRequest $procedureRequest, ProcedureRequestQueryService $queries): View
    {
        $procedureRequest = $queries->scoped(request()->user())->whereKey($procedureRequest->id)->firstOrFail();
        $procedureRequest->load([
            'user', 'procedureType', 'status', 'latestDerivation.destinationArea',
            'derivations' => fn ($query) => $query->with('destinationArea')->oldest('derived_at'),
            'response.author.area',
            'histories' => fn ($query) => $query->with('status')->oldest('created_at'),
        ]);

        return view('search.show', compact('procedureRequest'));
    }
}
