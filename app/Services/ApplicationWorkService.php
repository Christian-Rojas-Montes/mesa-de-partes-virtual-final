<?php

namespace App\Services;

use App\Enums\ApplicationWorkStage;
use App\Enums\TitleModality;
use App\Enums\TitleProcessStage;
use App\Models\ApplicationWorkProject;
use App\Models\AuditLog;
use App\Models\RequestDocument;
use App\Models\TitleProcess;
use App\Models\TitleSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplicationWorkService
{
    public function __construct(private readonly TitleProcessService $titles) {}

    public function createProposal(TitleProcess $process, User $actor, array $data, array $members): ApplicationWorkProject
    {
        $max = config('title-process.application_work.max_members');
        if ($process->modality !== TitleModality::APPLICATION_WORK || $process->applicationWorkProject()->exists() || count($members) < 1 || count($members) > $max) {
            throw ValidationException::withMessages(['members' => "El grupo debe tener entre 1 y {$max} integrantes."]);
        }
        if (collect($members)->contains(fn ($m) => ($m['study_program'] ?? null) !== $data['study_program'])) {
            throw ValidationException::withMessages(['members' => 'Todos los integrantes deben pertenecer al programa del trabajo.']);
        }
        $document = RequestDocument::findOrFail($data['project_document_id']);
        $this->documentBelongs($process, $document);

        return DB::transaction(function () use ($process, $actor, $data, $members) {
            $project = $process->applicationWorkProject()->create([...$data, 'current_stage' => ApplicationWorkStage::PROPOSAL]);
            foreach ($members as $i => $member) {
                $project->members()->create(['name_snapshot' => $member['name'], 'study_program_snapshot' => $member['study_program'], 'is_lead' => $i === 0]);
            }
            foreach (config('title-process.application_work.requirements') as $stage => $requirements) {
                foreach ($requirements as $requirement) {
                    $project->requirements()->create(['stage' => $stage, 'code' => $requirement['code'], 'label_snapshot' => $requirement['label'], 'physical' => $requirement['physical'] ?? false, 'quantity' => $requirement['quantity'] ?? 1]);
                }
            }
            $this->event($project, $actor, null, ApplicationWorkStage::PROPOSAL, 'proposal_created', 'Se registró la propuesta del Trabajo de Aplicación Profesional.', ['member_count' => count($members), 'title' => $data['title']]);

            return $project;
        });
    }

    public function approve(ApplicationWorkProject $project, User $actor, array $data): void
    {
        if ($project->current_stage !== ApplicationWorkStage::PROPOSAL) {
            $this->invalid();
        }
        if ($data['review_result'] === 'approved') {
            if (empty($data['approval_resolution_document_id']) || empty($data['assigned_advisor']) || empty($data['approved_at']) || empty($data['execution_deadline'])) {
                $this->invalid('La aprobación requiere resolución, asesor y fechas.');
            }
            $doc = RequestDocument::findOrFail($data['approval_resolution_document_id']);
            $this->documentBelongs($project->titleProcess, $doc);
            $months = Carbon::parse($data['approved_at'])->floatDiffInMonths(Carbon::parse($data['execution_deadline']));
            if ($months < config('title-process.application_work.execution_months_min') || $months > config('title-process.application_work.execution_months_max')) {
                $this->invalid('El plazo de ejecución no está dentro de la configuración vigente.');
            }
        }
        DB::transaction(function () use ($project, $actor, $data) {
            $next = $data['review_result'] === 'approved' ? ApplicationWorkStage::GRADUATE_CERTIFICATE : ApplicationWorkStage::APPROVAL;
            $project->update([...$data, 'current_stage' => $next]);
            $this->event($project, $actor, ApplicationWorkStage::PROPOSAL, $next, 'proposal_reviewed', 'Se registró la revisión de la propuesta.', ['result' => $data['review_result']]);
            if ($next === ApplicationWorkStage::GRADUATE_CERTIFICATE) {
                $this->titles->transition($project->titleProcess, $actor, TitleProcessStage::REQUIREMENTS_REVIEW, 'El proyecto fue aprobado y continúa con la documentación.');
            }
        });
    }

    public function registerRequirement(ApplicationWorkProject $project, User $actor, string $stage, string $code, string $status, ?int $documentId = null): void
    {
        if (! in_array($status, ['presented', 'verified', 'missing'], true)) {
            $this->invalid();
        }
        $requirement = $project->requirements()->where(['stage' => $stage, 'code' => $code])->firstOrFail();
        if ($documentId) {
            $doc = RequestDocument::findOrFail($documentId);
            $this->documentBelongs($project->titleProcess, $doc);
        }
        DB::transaction(function () use ($project, $actor, $requirement, $status, $documentId, $stage) {
            $requirement->update(['status' => $status, 'request_document_id' => $documentId, 'verified_by' => $status === 'verified' ? $actor->id : null, 'verified_at' => $status === 'verified' ? now() : null]);
            $this->event($project, $actor, $project->current_stage, $project->current_stage, 'requirement_updated', 'Se actualizó un requisito de la etapa.', ['code' => $requirement->code, 'status' => $status, 'physical' => $requirement->physical, 'quantity' => $requirement->quantity]);
            if (! $project->requirements()->where('stage', $stage)->where('status', '!=', 'verified')->exists()) {
                $next = $stage === 'graduate_certificate' ? ApplicationWorkStage::DEFENSE_FILE : ApplicationWorkStage::SCHEDULING;
                $from = $project->current_stage;
                $project->update(['current_stage' => $next]);
                $this->event($project, $actor, $from, $next, 'requirements_completed', 'Se completaron los requisitos de la etapa.', []);
                if ($next === ApplicationWorkStage::SCHEDULING) {
                    $this->titles->transition($project->titleProcess, $actor, TitleProcessStage::ACADEMIC_AREA, 'El expediente de sustentación está conforme.');
                }
            }
        });
    }

    public function registerOriginality(ApplicationWorkProject $project, User $actor, float $similarity, string $result): void
    {
        if ($similarity < 0 || $similarity > 100 || ($result === 'conforming' && $similarity > config('title-process.application_work.similarity_max_percent'))) {
            $this->invalid('La similitud no cumple la configuración vigente.');
        }
        $project->update(['similarity_percent' => $similarity, 'originality_result' => $result]);
        $this->event($project, $actor, $project->current_stage, $project->current_stage, 'originality_reviewed', 'Se registró el resultado de originalidad.', ['similarity_percent' => $similarity, 'limit' => config('title-process.application_work.similarity_max_percent')]);
    }

    public function schedule(ApplicationWorkProject $project, User $actor, array $data, ?TitleSchedule $previous = null): TitleSchedule
    {
        if ($project->current_stage !== ApplicationWorkStage::SCHEDULING) {
            $this->invalid();
        }$schedule = $this->titles->schedule($project->titleProcess, $actor, $data, $previous);
        $project->update(['current_stage' => ApplicationWorkStage::RESULT]);

        return $schedule;
    }

    public function result(ApplicationWorkProject $project, User $actor, string $result, ?float $grade, ?int $minutesDocumentId, ?string $observation): void
    {
        if ($project->current_stage !== ApplicationWorkStage::RESULT) {
            $this->invalid();
        }
        if ($result === 'approved' && $grade !== null && $grade < config('title-process.application_work.passing_grade')) {
            $this->invalid('La nota no alcanza la referencia aprobatoria configurada.');
        }
        if ($minutesDocumentId) {
            $doc = RequestDocument::findOrFail($minutesDocumentId);
            $this->documentBelongs($project->titleProcess, $doc);
        }
        $next = $result === 'rescheduled' ? ApplicationWorkStage::SCHEDULING : ApplicationWorkStage::RESULT;
        $project->update(['grade' => $grade, 'result_minutes_document_id' => $minutesDocumentId, 'current_stage' => $next]);
        $this->titles->recordResult($project->titleProcess, $actor, $result, $observation);
        $this->event($project, $actor, ApplicationWorkStage::RESULT, $next, 'result_recorded', 'Se registró el resultado de sustentación.', ['result' => $result, 'has_grade' => $grade !== null, 'has_minutes' => $minutesDocumentId !== null]);
    }

    private function documentBelongs(TitleProcess $process, RequestDocument $document): void
    {
        if ($document->procedure_request_id !== $process->procedure_request_id) {
            $this->invalid('El documento no pertenece al expediente.');
        }
    }

    private function event(ApplicationWorkProject $project, User $actor, ?ApplicationWorkStage $from, ApplicationWorkStage $to, string $action, string $description, array $snapshot): void
    {
        $project->events()->create(['from_stage' => $from?->value, 'to_stage' => $to->value, 'action' => $action, 'description' => $description, 'snapshot' => $snapshot, 'actor_id' => $actor->id, 'created_at' => now()]);
        $request = $project->titleProcess->procedureRequest;
        $request->histories()->create(['status_id' => $request->status_id, 'user_id' => $actor->id, 'action' => 'application_work_'.$action, 'description' => $description]);
        AuditLog::create(['user_id' => $actor->id, 'action' => 'application_work_'.$action, 'auditable_type' => $request->getMorphClass(), 'auditable_id' => $request->id, 'details' => $snapshot, 'ip_address' => request()->ip(), 'user_agent' => request()->userAgent()]);
    }

    private function invalid(string $message = 'La operación no corresponde a la etapa actual'): never
    {
        throw ValidationException::withMessages(['application_work' => $message]);
    }
}
