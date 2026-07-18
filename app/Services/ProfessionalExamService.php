<?php

namespace App\Services;

use App\Enums\TitleModality;
use App\Enums\TitleProcessStage;
use App\Models\AuditLog;
use App\Models\ProfessionalExamAttempt;
use App\Models\ProfessionalExamProfile;
use App\Models\RequestDocument;
use App\Models\TitleProcess;
use App\Models\TitleSchedule;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProfessionalExamService
{
    public function __construct(private readonly TitleProcessService $titles) {}

    public function create(TitleProcess $process, User $actor, int $months, string $basis): ProfessionalExamProfile
    {
        return DB::transaction(function () use ($process, $actor, $months, $basis) {
            if ($process->modality !== TitleModality::PROFESSIONAL_EXAM || $process->professionalExamProfile()->exists()) {
                throw ValidationException::withMessages(['experience_months' => 'El proceso no admite un nuevo examen.']);
            }
            if ($months < config('title-process.professional_exam.minimum_experience_months')) {
                throw ValidationException::withMessages(['experience_months' => 'No se alcanza la experiencia profesional o EFSRT mínima configurada.']);
            }
            $profile = $process->professionalExamProfile()->create(['experience_months' => $months, 'experience_basis' => $basis, 'current_stage' => 'requirements']);
            foreach (config('title-process.professional_exam.requirements') as $item) {
                $profile->requirements()->create(['code' => $item['code'], 'label_snapshot' => $item['label'], 'physical' => $item['physical'] ?? false, 'sensitive' => $item['sensitive'] ?? false, 'quantity' => $item['quantity'] ?? 1]);
            }
            $this->log($process, $actor, 'exam_created', 'Se registró el expediente de Examen de Suficiencia Profesional.', ['experience_months' => $months, 'basis' => $basis]);

            return $profile;
        });
    }

    public function verifyRequirement(ProfessionalExamProfile $profile, User $actor, string $code, string $status, ?RequestDocument $document = null, ?string $observation = null): void
    {
        DB::transaction(function () use ($profile, $actor, $code, $status, $document, $observation) {
            if (! in_array($status, ['verified', 'missing', 'observed'], true)) {
                throw ValidationException::withMessages(['status' => 'La conformidad no es válida.']);
            }
            if ($document && $document->procedure_request_id !== $profile->titleProcess->procedure_request_id) {
                throw ValidationException::withMessages(['document' => 'El documento no pertenece al expediente.']);
            }
            $requirement = $profile->requirements()->where('code', $code)->firstOrFail();
            $requirement->update(['status' => $status, 'request_document_id' => $document?->id, 'observation' => $observation, 'verified_by' => $actor->id, 'verified_at' => now()]);
            $this->log($profile->titleProcess, $actor, 'exam_requirement_reviewed', 'Se revisó un requisito del examen.', ['code' => $code, 'status' => $status]);
        });
    }

    public function schedule(ProfessionalExamProfile $profile, User $actor, array $data, ?TitleSchedule $previous = null): ProfessionalExamAttempt
    {
        return DB::transaction(function () use ($profile, $actor, $data, $previous) {
            if ($profile->requirements()->where('status', '!=', 'verified')->exists()) {
                throw ValidationException::withMessages(['requirements' => 'Todos los requisitos deben estar conformes.']);
            }
            $process = $profile->titleProcess;
            if ($process->current_stage === TitleProcessStage::RESULT_RECORDED) {
                $this->titles->reopenForNewOpportunity($process, $actor);
            }
            $process->refresh();
            if ($process->current_stage === TitleProcessStage::INITIAL_FILE) {
                $this->titles->transition($process, $actor, TitleProcessStage::REQUIREMENTS_REVIEW, 'Los requisitos del examen están conformes.');
            }
            $process->refresh();
            if ($process->current_stage === TitleProcessStage::REQUIREMENTS_REVIEW) {
                $this->titles->transition($process, $actor, TitleProcessStage::ACADEMIC_AREA, 'El examen pasa a programación académica.');
            }
            $process->refresh();
            $opportunity = (int) ($profile->attempts()->max('opportunity') ?? 0);
            if (! $previous) {
                $opportunity++;
            }
            if ($opportunity > config('title-process.professional_exam.maximum_opportunities')) {
                throw ValidationException::withMessages(['opportunity' => 'Se alcanzó el máximo de oportunidades configurado.']);
            }
            $schedule = $this->titles->schedule($process, $actor, $data, $previous);
            $attempt = $profile->attempts()->firstOrCreate(['opportunity' => $opportunity], ['theory_weight' => config('title-process.professional_exam.theory_weight'), 'practical_weight' => config('title-process.professional_exam.practical_weight')]);
            $attempt->update(['title_schedule_id' => $schedule->id]);
            $profile->update(['current_stage' => 'scheduled']);

            return $attempt;
        });
    }

    public function recordResult(ProfessionalExamAttempt $attempt, User $actor, string $result, ?float $theory, ?float $practical, ?string $observation = null): void
    {
        DB::transaction(function () use ($attempt, $actor, $result, $theory, $practical, $observation) {
            if ($attempt->result) {
                throw ValidationException::withMessages(['result' => 'La oportunidad ya tiene resultado.']);
            }
            if (! in_array($result, ['approved', 'failed', 'absent', 'rescheduled'], true)) {
                throw ValidationException::withMessages(['result' => 'El resultado no es válido.']);
            }
            $final = null;
            if (in_array($result, ['approved', 'failed'], true)) {
                if ($theory === null || $practical === null || $theory < 0 || $theory > 20 || $practical < 0 || $practical > 20) {
                    throw ValidationException::withMessages(['grades' => 'Las notas deben estar entre 0 y 20.']);
                }
                $sum = (float) $attempt->theory_weight + (float) $attempt->practical_weight;
                if (abs($sum - 100) > 0.001) {
                    throw ValidationException::withMessages(['weights' => 'Los pesos configurados deben sumar 100 %.']);
                }
                $final = round($theory * (float) $attempt->theory_weight / 100 + $practical * (float) $attempt->practical_weight / 100, 2);
                $result = $final >= config('title-process.professional_exam.passing_grade') ? 'approved' : 'failed';
            }
            $attempt->update(['theory_grade' => $theory, 'practical_grade' => $practical, 'final_grade' => $final, 'result' => $result, 'observation' => $observation, 'recorded_by' => $actor->id, 'recorded_at' => now()]);
            $profile = $attempt->profile;
            $this->titles->recordResult($profile->titleProcess, $actor, $result, $observation);
            $profile->update(['current_stage' => $result === 'approved' ? 'approved' : ($result === 'rescheduled' ? 'scheduled' : 'opportunity_completed')]);
            $this->log($profile->titleProcess, $actor, 'exam_result_recorded', 'Se registró el resultado de una oportunidad del examen.', ['opportunity' => $attempt->opportunity, 'result' => $result, 'final_grade' => $final]);
        });
    }

    private function log(TitleProcess $process, User $actor, string $action, string $description, array $details): void
    {
        $request = $process->procedureRequest;
        $request->histories()->create(['status_id' => $request->status_id, 'user_id' => $actor->id, 'action' => 'title_'.$action, 'description' => $description]);
        AuditLog::create(['user_id' => $actor->id, 'action' => 'title_'.$action, 'auditable_type' => $request->getMorphClass(), 'auditable_id' => $request->id, 'details' => $details, 'ip_address' => request()->ip(), 'user_agent' => request()->userAgent()]);
    }
}
