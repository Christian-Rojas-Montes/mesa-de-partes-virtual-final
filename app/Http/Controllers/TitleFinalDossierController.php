<?php

namespace App\Http\Controllers;

use App\Http\Requests\Title\ReviewFinalDossierRequirementRequest;
use App\Models\RequestDocument;
use App\Models\TitleFinalDossier;
use App\Models\TitleFinalDossierRequirement;
use App\Models\TitleProcess;
use App\Services\TitleFinalDossierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TitleFinalDossierController extends Controller
{
    public function store(Request $r, TitleProcess $titleProcess, TitleFinalDossierService $s): RedirectResponse
    {
        $this->authorize('manage', $titleProcess);
        $s->create($titleProcess, $r->user());

        return back()->with('success', 'Expediente final creado.');
    }

    public function requirement(ReviewFinalDossierRequirementRequest $r, TitleProcess $titleProcess, TitleFinalDossierRequirement $requirement, TitleFinalDossierService $s): RedirectResponse
    {
        $this->authorize('manage', $titleProcess);
        abort_unless($requirement->dossier->title_process_id === $titleProcess->id, 404);
        $doc = $r->filled('request_document_id') ? RequestDocument::findOrFail($r->integer('request_document_id')) : null;
        $s->reviewRequirement($requirement, $r->user(), $r->string('status'), $doc, $r->validated('observation'));

        return back()->with('success', 'Requisito actualizado.');
    }

    public function action(Request $r, TitleProcess $titleProcess, TitleFinalDossier $dossier, TitleFinalDossierService $s): RedirectResponse
    {
        $this->authorize('manage', $titleProcess);
        abort_unless($dossier->title_process_id === $titleProcess->id, 404);
        $data = $r->validate(['action' => 'required|in:conform,submit,register,ready,deliver', 'observations' => 'nullable|string|max:2000', 'registration_code' => 'nullable|string|max:100', 'registered_at' => 'nullable|date', 'issued_at' => 'nullable|date', 'pickup_at' => 'nullable|date']);
        match ($data['action']) {
            'conform' => $s->conform($dossier, $r->user(), $data['observations'] ?? null),'submit' => $s->submitForRegistration($dossier, $r->user()),'register' => $s->register($dossier, $r->user(), $data['registration_code'] ?? '', $data['registered_at'] ?? now()->toDateString()),'ready' => $s->markReady($dossier, $r->user(), $data['issued_at'] ?? now()->toDateString(), $data['pickup_at'] ?? now()->toDateString()),'deliver' => $s->deliver($dossier,$r->user())
        };

        return back()->with('success','Expediente final actualizado.');
    }
}
