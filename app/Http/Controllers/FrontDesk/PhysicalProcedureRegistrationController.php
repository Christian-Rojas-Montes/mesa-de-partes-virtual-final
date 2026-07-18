<?php

namespace App\Http\Controllers\FrontDesk;

use App\Http\Controllers\Controller;
use App\Http\Requests\FrontDesk\PhysicalApplicantSearchRequest;
use App\Http\Requests\FrontDesk\StorePhysicalProcedureRequest;
use App\Models\Area;
use App\Models\ProcedureRequest;
use App\Models\ProcedureRequirement;
use App\Models\ProcedureType;
use App\Models\User;
use App\Services\PhysicalProcedureRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PhysicalProcedureRegistrationController extends Controller
{
    public function create(PhysicalApplicantSearchRequest $request): View
    {
        $filters = $request->validated();
        $hasSearch = collect($filters)->filter()->isNotEmpty();
        $applicants = $hasSearch ? User::query()->whereHas('role', fn ($q) => $q->where('name', 'Solicitante'))
            ->when($filters['document_type'] ?? null, fn ($q, $v) => $q->where('document_type', $v))->when($filters['document_number'] ?? null, fn ($q, $v) => $q->where('document_number', $v))
            ->when($filters['email'] ?? null, fn ($q, $v) => $q->where('email', $v))->when($filters['student_code'] ?? null, fn ($q, $v) => $q->where('student_code', $v))->limit(10)->get() : collect();
        $types = ProcedureType::active()->with(['category', 'variants' => fn ($q) => $q->where('active', true)->orderBy('name')])->orderBy('name')->get();
        $selectedType = $types->firstWhere('id', $request->integer('tipo'));
        $selectedVariant = $selectedType?->variants->firstWhere('id', $request->integer('variante'));
        $requirements = $selectedType ? ProcedureRequirement::active()->where('procedure_type_id', $selectedType->id)->where('requires_physical_submission', true)->where(fn ($q) => $q->whereNull('procedure_variant_id')->when($selectedVariant, fn ($q) => $q->orWhere('procedure_variant_id', $selectedVariant->id)))->orderByDesc('required')->orderBy('sort_order')->get() : collect();

        return view('front-desk.physical-registration.create', ['filters' => $filters, 'hasSearch' => $hasSearch, 'applicants' => $applicants, 'types' => $types, 'selectedType' => $selectedType, 'selectedVariant' => $selectedVariant, 'requirements' => $requirements, 'areas' => Area::active()->orderBy('name')->get()]);
    }

    public function store(StorePhysicalProcedureRequest $request, PhysicalProcedureRegistrationService $service): RedirectResponse
    {
        $procedureRequest = $service->register($request->user(), $request->validated());

        return to_route('front-desk.physical-registration.receipt', $procedureRequest)->with('status', 'El expediente presencial fue registrado.');
    }

    public function receipt(ProcedureRequest $procedureRequest): View
    {
        Gate::authorize('printPhysicalReceipt', $procedureRequest);
        $procedureRequest->load(['user', 'procedureType', 'variant', 'physicalReception.receiver', 'physicalReception.receivingArea']);

        return view('front-desk.physical-registration.receipt', compact('procedureRequest'));
    }
}
