<?php

namespace App\Http\Controllers;

use App\Enums\TitleProcessStage;
use App\Http\Requests\Title\RecordTitleResultRequest;
use App\Http\Requests\Title\ScheduleTitleProcessRequest;
use App\Http\Requests\Title\TransitionTitleProcessRequest;
use App\Http\Requests\Title\VerifyTitleEligibilityRequest;
use App\Models\TitleProcess;
use App\Models\TitleSchedule;
use App\Services\TitleProcessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TitleProcessController extends Controller
{
    public function show(TitleProcess $titleProcess): View
    {
        Gate::authorize('view', $titleProcess);
        $titleProcess->load(['procedureRequest.user', 'procedureRequest.documents', 'responsible', 'events.actor', 'schedules.creator', 'stageDocuments.document', 'applicationWorkProject.members', 'applicationWorkProject.requirements']);

        return view('title-processes.show', ['titleProcess' => $titleProcess, 'canManage' => Gate::allows('manage', $titleProcess), 'canCreateApplicationWork' => Gate::allows('createApplicationWork', $titleProcess), 'stages' => TitleProcessStage::cases()]);
    }

    public function transition(TransitionTitleProcessRequest $request, TitleProcess $titleProcess, TitleProcessService $service): RedirectResponse
    {
        $service->transition($titleProcess, $request->user(), TitleProcessStage::from($request->validated('stage')), $request->validated('description'));

        return back()->with('status', 'La etapa fue actualizada.');
    }

    public function verify(VerifyTitleEligibilityRequest $request, TitleProcess $titleProcess, TitleProcessService $service): RedirectResponse
    {
        $data = $request->safe()->except('responsible_id');
        $service->verifyEligibility($titleProcess, $request->user(), $data, $request->integer('responsible_id') ?: null);

        return back()->with('status', 'La verificación de elegibilidad fue registrada.');
    }

    public function schedule(ScheduleTitleProcessRequest $request, TitleProcess $titleProcess, TitleProcessService $service): RedirectResponse
    {
        $service->schedule($titleProcess, $request->user(), $request->validated());

        return back()->with('status', 'La actividad fue programada.');
    }

    public function reschedule(ScheduleTitleProcessRequest $request, TitleProcess $titleProcess, TitleSchedule $schedule, TitleProcessService $service): RedirectResponse
    {
        $service->schedule($titleProcess, $request->user(), $request->validated(), $schedule);

        return back()->with('status', 'La actividad fue reprogramada.');
    }

    public function result(RecordTitleResultRequest $request, TitleProcess $titleProcess, TitleProcessService $service): RedirectResponse
    {
        $service->recordResult($titleProcess, $request->user(), $request->validated('result'), $request->validated('observation'));

        return back()->with('status', 'El resultado fue registrado.');
    }
}
