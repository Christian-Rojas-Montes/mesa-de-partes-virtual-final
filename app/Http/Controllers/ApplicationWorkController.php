<?php

namespace App\Http\Controllers;

use App\Http\Requests\Title\ApplicationWorkOriginalityRequest;
use App\Http\Requests\Title\ApproveApplicationWorkRequest;
use App\Http\Requests\Title\StoreApplicationWorkProposalRequest;
use App\Http\Requests\Title\UpdateApplicationWorkRequirementRequest;
use App\Models\ApplicationWorkProject;
use App\Models\TitleProcess;
use App\Services\ApplicationWorkService;
use Illuminate\Http\RedirectResponse;

class ApplicationWorkController extends Controller
{
    public function store(StoreApplicationWorkProposalRequest $request, TitleProcess $titleProcess, ApplicationWorkService $service): RedirectResponse
    {
        $data = $request->safe()->except('members');
        $service->createProposal($titleProcess, $request->user(), $data, $request->validated('members'));

        return back()->with('status', 'La propuesta fue registrada.');
    }

    public function approve(ApproveApplicationWorkRequest $request, TitleProcess $titleProcess, ApplicationWorkProject $project, ApplicationWorkService $service): RedirectResponse
    {
        abort_unless($project->title_process_id === $titleProcess->id, 404);
        $service->approve($project, $request->user(), $request->validated());

        return back()->with('status', 'La revisión de la propuesta fue registrada.');
    }

    public function requirement(UpdateApplicationWorkRequirementRequest $request, TitleProcess $titleProcess, ApplicationWorkProject $project, ApplicationWorkService $service): RedirectResponse
    {
        abort_unless($project->title_process_id === $titleProcess->id, 404);
        $service->registerRequirement($project, $request->user(), $request->validated('stage'), $request->validated('code'), $request->validated('status'), $request->integer('request_document_id') ?: null);

        return back()->with('status', 'El requisito fue actualizado.');
    }

    public function originality(ApplicationWorkOriginalityRequest $request, TitleProcess $titleProcess, ApplicationWorkProject $project, ApplicationWorkService $service): RedirectResponse
    {
        abort_unless($project->title_process_id === $titleProcess->id, 404);
        $service->registerOriginality($project, $request->user(), (float) $request->validated('similarity_percent'), $request->validated('originality_result'));

        return back()->with('status','La revisión de originalidad fue registrada.');
    }
}
