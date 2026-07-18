<?php

namespace App\Http\Controllers;

use App\Http\Requests\Title\CreateProfessionalExamRequest;
use App\Http\Requests\Title\RecordProfessionalExamResultRequest;
use App\Http\Requests\Title\ReviewProfessionalExamRequirementRequest;
use App\Http\Requests\Title\ScheduleTitleProcessRequest;
use App\Models\ProfessionalExamAttempt;
use App\Models\ProfessionalExamProfile;
use App\Models\RequestDocument;
use App\Models\TitleProcess;
use App\Models\TitleSchedule;
use App\Services\ProfessionalExamService;
use Illuminate\Http\RedirectResponse;

class ProfessionalExamController extends Controller
{
    public function store(CreateProfessionalExamRequest $r, TitleProcess $titleProcess, ProfessionalExamService $s): RedirectResponse
    {
        $this->authorize('createProfessionalExam', $titleProcess);
        $s->create($titleProcess, $r->user(), $r->integer('experience_months'), $r->string('experience_basis'));

        return back()->with('success', 'Expediente de examen registrado.');
    }

    public function requirement(ReviewProfessionalExamRequirementRequest $r, TitleProcess $titleProcess, ProfessionalExamProfile $profile, ProfessionalExamService $s): RedirectResponse
    {
        abort_unless($profile->title_process_id === $titleProcess->id, 404);
        $document = $r->filled('request_document_id') ? RequestDocument::findOrFail($r->integer('request_document_id')) : null;
        $s->verifyRequirement($profile, $r->user(), $r->string('code'), $r->string('status'), $document, $r->validated('observation'));

        return back()->with('success', 'Requisito del examen actualizado.');
    }

    public function schedule(ScheduleTitleProcessRequest $r, TitleProcess $titleProcess, ProfessionalExamProfile $profile, ProfessionalExamService $s): RedirectResponse
    {
        $this->authorize('manage', $titleProcess);
        abort_unless($profile->title_process_id === $titleProcess->id, 404);
        $s->schedule($profile, $r->user(), $r->validated());

        return back()->with('success', 'Examen programado.');
    }

    public function reschedule(ScheduleTitleProcessRequest $r, TitleProcess $titleProcess, ProfessionalExamProfile $profile, TitleSchedule $schedule, ProfessionalExamService $s): RedirectResponse
    {
        $this->authorize('manage', $titleProcess);
        abort_unless($profile->title_process_id === $titleProcess->id && $schedule->title_process_id === $titleProcess->id, 404);
        $s->schedule($profile, $r->user(), $r->validated(), $schedule);

        return back()->with('success', 'Examen reprogramado.');
    }

    public function result(RecordProfessionalExamResultRequest $r, TitleProcess $titleProcess, ProfessionalExamAttempt $attempt, ProfessionalExamService $s): RedirectResponse
    {
        $this->authorize('manage', $titleProcess);
        abort_unless($attempt->profile->title_process_id === $titleProcess->id, 404);
        $s->recordResult($attempt, $r->user(), $r->string('result'), $r->validated('theory_grade'), $r->validated('practical_grade'), $r->validated('observation'));

        return back()->with('success', 'Resultado registrado.');
    }
}
