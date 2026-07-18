<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublicProcedureCatalogRequest;
use App\Http\Requests\VariantSelectionRequest;
use App\Models\PresentationModality;
use App\Models\ProcedureCategory;
use App\Models\ProcedureRequest;
use App\Models\ProcedureType;
use App\Models\ProcedureVariant;
use App\Services\PublicProcedureCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PublicProcedureCatalogController extends Controller
{
    public function index(PublicProcedureCatalogRequest $request, PublicProcedureCatalogService $catalog): View
    {
        $filters = $request->validated();
        $procedures = $catalog->query($filters)->paginate(12)->withQueryString();

        return view('catalog.index', ['procedures' => $procedures, 'filters' => $filters, 'categories' => ProcedureCategory::active()->orderBy('sort_order')->get(), 'modalities' => PresentationModality::query()->where('active', true)->orderBy('name')->get(), 'catalog' => $catalog]);
    }

    public function show(Request $request, ProcedureType $procedureType, PublicProcedureCatalogService $catalog): View
    {
        abort_unless($procedureType->active, 404);
        $procedureType->load(['category', 'presentationModality', 'responsibleArea', 'variants' => fn ($query) => $query->where('active', true)->orderBy('sort_order'), 'requirements' => fn ($query) => $query->where('active', true)->with('variant')->orderBy('sort_order'), 'dynamicFields' => fn ($query) => $query->where('active', true)->with('variant')->orderBy('sort_order'), 'prerequisites' => fn ($query) => $query->where('active', true)->with(['requiredProcedureType', 'variant'])->orderBy('sort_order')]);
        $variant = $this->selectedVariant($request, $procedureType);
        $selection = $request->session()->get($this->selectionKey($procedureType));

        return view('catalog.show', ['procedureType' => $procedureType, 'variant' => $variant, 'selection' => $selection, 'selectorFields' => $catalog->selectorFields($procedureType), 'availability' => $catalog->availability($procedureType), 'canStart' => $catalog->canStart($procedureType, $variant), 'catalog' => $catalog]);
    }

    public function select(VariantSelectionRequest $request, ProcedureType $procedureType, PublicProcedureCatalogService $catalog): RedirectResponse
    {
        abort_unless($procedureType->active, 404);
        $result = $catalog->selectVariant($procedureType, $request->validated('answers'), $request->validated('variant_code'));
        if ($result['variant'] === null) {
            throw ValidationException::withMessages(['answers' => 'Las respuestas declaradas no coinciden con una variante disponible.']);
        }
        $request->session()->put($this->selectionKey($procedureType), ['variant_id' => $result['variant']->id, 'answers' => $result['answers'], 'basis' => 'declared']);

        return to_route('catalog.show', ['procedureType' => $procedureType->code, 'variante' => $result['variant']->code])->with('status', 'Variante seleccionada según la información declarada.');
    }

    public function start(Request $request, ProcedureType $procedureType, PublicProcedureCatalogService $catalog): RedirectResponse
    {
        abort_unless($procedureType->active, 404);
        $variant = $this->selectedVariant($request, $procedureType);
        if ($procedureType->variants()->where('active', true)->exists() && $variant === null) {
            throw ValidationException::withMessages(['variant' => 'Selecciona primero la variante aplicable.']);
        }
        if (! $catalog->canStart($procedureType, $variant)) {
            throw ValidationException::withMessages(['procedure' => 'El trámite no se encuentra disponible para iniciar.']);
        }
        $request->session()->put($this->selectionKey($procedureType), ['variant_id' => $variant?->id, 'answers' => $request->session()->get($this->selectionKey($procedureType).'.answers', []), 'basis' => 'declared']);
        if (! $request->user()) {
            $request->session()->put('url.intended', route('catalog.resume', $procedureType->code));

            return to_route('login');
        }

        return to_route('catalog.resume', $procedureType->code);
    }

    public function resume(Request $request, ProcedureType $procedureType, PublicProcedureCatalogService $catalog): RedirectResponse
    {
        abort_unless($procedureType->active, 404);
        Gate::authorize('create', ProcedureRequest::class);
        $selection = $request->session()->get($this->selectionKey($procedureType));
        $variant = isset($selection['variant_id']) ? ProcedureVariant::query()->where('procedure_type_id', $procedureType->id)->where('active', true)->find($selection['variant_id']) : null;
        if ($procedureType->variants()->where('active', true)->exists() && $variant === null) {
            return to_route('catalog.show', $procedureType->code)->withErrors(['variant' => 'La selección debe confirmarse nuevamente.']);
        }
        if (! $catalog->canStart($procedureType, $variant)) {
            return to_route('catalog.show', $procedureType->code)->withErrors(['procedure' => 'El trámite dejó de estar disponible.']);
        }

        return to_route('applicant.procedure-requests.create', array_filter(['tipo' => $procedureType->id, 'variante' => $variant?->code]));
    }

    private function selectedVariant(Request $request, ProcedureType $procedureType): ?ProcedureVariant
    {
        $code = $request->string('variante')->toString();
        if ($code !== '') {
            return $procedureType->variants()->where('active', true)->where('code', $code)->firstOrFail();
        }
        $id = $request->session()->get($this->selectionKey($procedureType).'.variant_id');

        return $id ? $procedureType->variants()->where('active', true)->find($id) : null;
    }

    private function selectionKey(ProcedureType $procedureType): string
    {
        return "catalog.selection.{$procedureType->id}";
    }
}
