<?php

namespace App\Services;

use App\Enums\TitleModality;
use App\Enums\TitleProcessStage;
use App\Models\AuditLog;
use App\Models\ProcedureRequest;
use App\Models\RequestDocument;
use App\Models\TitleProcess;
use App\Models\TitleSchedule;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TitleProcessService
{
    private const TRANSITIONS = [
        'initial_file' => ['requirements_review'], 'requirements_review' => ['academic_area'],
        'academic_area' => ['scheduled'], 'scheduled' => ['result_recorded'],
        'result_recorded' => ['final_file'], 'final_file' => ['external_registration'],
        'external_registration' => ['ready_for_delivery'], 'ready_for_delivery' => ['delivered'], 'delivered' => [],
    ];

    public function __construct(private readonly InternalNotificationService $notifications) {}

    public function create(ProcedureRequest $request, User $actor, TitleModality $modality, array $eligibility, ?string $attempt = null): TitleProcess
    {
        return DB::transaction(function () use ($request, $actor, $modality, $eligibility, $attempt) {
            if ($request->titleProcess()->exists() || $request->procedureType->code !== 'TITLE_PROF_TECH') {
                throw ValidationException::withMessages(['modality' => 'La solicitud no admite un nuevo proceso de titulación.']);
            }
            $process = $request->titleProcess()->create(['modality' => $modality, 'current_stage' => TitleProcessStage::INITIAL_FILE, 'attempt_or_call' => $attempt, 'eligibility_declared' => $eligibility]);
            $this->event($process, $actor, null, TitleProcessStage::INITIAL_FILE, 'created', 'Se registró el expediente inicial de titulación.', ['modality' => $modality->label(), 'eligibility_basis' => 'declared']);

            return $process;
        });
    }

    public function verifyEligibility(TitleProcess $process, User $actor, array $verification, ?int $responsibleId = null): void
    {
        DB::transaction(function () use ($process, $actor, $verification, $responsibleId) {
            $process->update(['eligibility_verified' => $verification, 'eligibility_verified_by' => $actor->id, 'eligibility_verified_at' => now(), 'responsible_id' => $responsibleId ?: $process->responsible_id]);
            $this->event($process, $actor, $process->current_stage, $process->current_stage, 'eligibility_verified', 'El personal registró la verificación de elegibilidad.', ['verified_fields' => array_keys($verification)]);
        });
    }

    public function transition(TitleProcess $process, User $actor, TitleProcessStage $next, string $description): void
    {
        DB::transaction(function () use ($process, $actor, $next, $description) {
            $locked = TitleProcess::lockForUpdate()->findOrFail($process->id);
            $from = $locked->current_stage;
            if (! in_array($next->value, self::TRANSITIONS[$from->value], true)) {
                throw ValidationException::withMessages(['stage' => 'La transición de etapa no es válida.']);
            }
            if ($next === TitleProcessStage::FINAL_FILE && $locked->result !== 'approved') {
                throw ValidationException::withMessages(['stage' => 'Solo un resultado aprobado permite conformar el expediente final.']);
            }
            $locked->update(['current_stage' => $next, 'final_file_completed_at' => $next === TitleProcessStage::FINAL_FILE ? now() : $locked->final_file_completed_at]);
            $this->event($locked, $actor, $from, $next, 'stage_changed', $description, ['from' => $from->label(), 'to' => $next->label()]);
            $this->notifications->dispatch($locked->procedureRequest, 'title_stage_changed', "Tu trámite de titulación avanzó a: {$next->label()}.", $next->value);
        });
    }

    public function schedule(TitleProcess $process, User $actor, array $data, ?TitleSchedule $previous = null): TitleSchedule
    {
        return DB::transaction(function () use ($process, $actor, $data, $previous) {
            if (! in_array($process->current_stage, [TitleProcessStage::ACADEMIC_AREA, TitleProcessStage::SCHEDULED], true)) {
                throw ValidationException::withMessages(['scheduled_at' => 'El proceso no está listo para programación.']);
            }
            if ($previous && ($previous->title_process_id !== $process->id || $previous->status !== 'scheduled')) {
                throw ValidationException::withMessages(['scheduled_at' => 'La programación seleccionada no puede reprogramarse.']);
            }
            $previous?->update(['status' => 'rescheduled']);
            $schedule = $process->schedules()->create([...$data, 'rescheduled_from_id' => $previous?->id, 'status' => 'scheduled', 'created_by' => $actor->id]);
            if ($process->current_stage === TitleProcessStage::ACADEMIC_AREA) {
                $process->update(['current_stage' => TitleProcessStage::SCHEDULED]);
            }
            $this->event($process, $actor, $process->current_stage, TitleProcessStage::SCHEDULED, $previous ? 'rescheduled' : 'scheduled', $previous ? 'La actividad de titulación fue reprogramada.' : 'La actividad de titulación fue programada.', ['scheduled_at' => $schedule->scheduled_at->toIso8601String(), 'place' => $schedule->place, 'reason' => $schedule->reason]);
            $this->notifications->dispatch($process->procedureRequest, $previous ? 'title_rescheduled' : 'title_scheduled', "Programación de titulación: {$schedule->scheduled_at->format('d/m/Y H:i')} en {$schedule->place}.", (string) $schedule->id);

            return $schedule;
        });
    }

    public function recordResult(TitleProcess $process, User $actor, string $result, ?string $observation): void
    {
        $allowed = ['approved', 'failed', 'absent', 'rescheduled', 'observed'];
        if (! in_array($result, $allowed, true) || $process->current_stage !== TitleProcessStage::SCHEDULED) {
            throw ValidationException::withMessages(['result' => 'El resultado o la etapa actual no son válidos.']);
        }
        DB::transaction(function () use ($process, $actor, $result, $observation) {
            $next = $result === 'rescheduled' ? TitleProcessStage::SCHEDULED : TitleProcessStage::RESULT_RECORDED;
            $process->update(['result' => $result, 'result_observation' => $observation, 'result_recorded_by' => $actor->id, 'result_recorded_at' => now(), 'current_stage' => $next]);
            $this->event($process, $actor, TitleProcessStage::SCHEDULED, $next, 'result_recorded', 'Se registró el resultado del proceso de titulación.', ['result' => $result]);
        });
    }

    public function reopenForNewOpportunity(TitleProcess $process, User $actor): void
    {
        DB::transaction(function () use ($process, $actor) {
            if ($process->current_stage !== TitleProcessStage::RESULT_RECORDED || ! in_array($process->result, ['failed', 'absent'], true)) {
                throw ValidationException::withMessages(['opportunity' => 'El proceso no admite una nueva oportunidad.']);
            }
            $process->update(['current_stage' => TitleProcessStage::ACADEMIC_AREA]);
            $this->event($process, $actor, TitleProcessStage::RESULT_RECORDED, TitleProcessStage::ACADEMIC_AREA, 'new_opportunity', 'Se habilitó una nueva oportunidad de evaluación.', []);
        });
    }

    public function attachDocument(TitleProcess $process, RequestDocument $document, User $actor, string $kind, string $label): void
    {
        if ($document->procedure_request_id !== $process->procedure_request_id || ! in_array($kind, ['document', 'resolution', 'minutes'], true)) {
            throw ValidationException::withMessages(['document' => 'El documento no corresponde al expediente.']);
        }
        DB::transaction(function () use ($process, $document, $actor, $kind, $label) {
            $process->stageDocuments()->create(['request_document_id' => $document->id, 'stage' => $process->current_stage, 'document_kind' => $kind, 'label_snapshot' => $label, 'registered_by' => $actor->id, 'created_at' => now()]);
            $this->event($process, $actor, $process->current_stage, $process->current_stage, 'document_linked', 'Se vinculó un documento privado a la etapa.', ['kind' => $kind, 'label' => $label]);
        });
    }

    private function event(TitleProcess $process, User $actor, ?TitleProcessStage $from, TitleProcessStage $to, string $action, string $description, array $snapshot): void
    {
        $process->events()->create(['from_stage' => $from, 'to_stage' => $to, 'action' => $action, 'description' => $description, 'snapshot' => $snapshot, 'actor_id' => $actor->id, 'created_at' => now()]);
        $request = $process->procedureRequest;
        $request->histories()->create(['status_id' => $request->status_id, 'user_id' => $actor->id, 'action' => 'title_'.$action, 'description' => $description]);
        AuditLog::create(['user_id' => $actor->id, 'action' => 'title_'.$action, 'auditable_type' => $request->getMorphClass(), 'auditable_id' => $request->id, 'details' => $snapshot, 'ip_address' => request()->ip(), 'user_agent' => request()->userAgent()]);
    }
}
