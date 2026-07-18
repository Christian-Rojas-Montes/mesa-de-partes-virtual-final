<?php

namespace App\Services;

use App\Enums\TitleProcessStage;
use App\Models\AuditLog;
use App\Models\RequestDocument;
use App\Models\TitleFinalDossier;
use App\Models\TitleFinalDossierRequirement;
use App\Models\TitleProcess;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TitleFinalDossierService
{
    public function __construct(private readonly TitleProcessService $titles) {}

    public function create(TitleProcess $process, User $actor): TitleFinalDossier
    {
        return DB::transaction(function () use ($process, $actor) {
            if ($process->result !== 'approved') {
                throw ValidationException::withMessages(['dossier' => 'El expediente final solo está disponible después de la aprobación.']);
            }
            if ($process->finalDossier()->exists()) {
                throw ValidationException::withMessages(['dossier' => 'El expediente final ya existe.']);
            }
            if ($process->current_stage === TitleProcessStage::RESULT_RECORDED) {
                $this->titles->transition($process, $actor, TitleProcessStage::FINAL_FILE, 'Se inició la conformación del expediente final del título.');
            }
            $dossier = $process->finalDossier()->create(['status' => 'collecting']);
            foreach (config('title-process.final_dossier.requirements') as $item) {
                $dossier->requirements()->create(['code' => $item['code'], 'label_snapshot' => $item['label'], 'physical' => $item['physical'] ?? false, 'original' => $item['original'] ?? false, 'sensitive' => $item['sensitive'] ?? false, 'conditional' => $item['conditional'] ?? false, 'quantity' => $item['quantity'] ?? 1, 'validity_days' => $item['validity_days'] ?? null]);
            }
            $this->event($dossier, $actor, 'created', 'Se creó el expediente final.', []);

            return $dossier;
        });
    }

    public function reviewRequirement(TitleFinalDossierRequirement $requirement, User $actor, string $status, ?RequestDocument $document = null, ?string $observation = null): void
    {
        DB::transaction(function () use ($requirement, $actor, $status, $document, $observation) {
            if (! in_array($status, ['presented', 'verified', 'missing', 'observed', 'subsanated', 'not_applicable'], true)) {
                throw ValidationException::withMessages(['status' => 'El estado del requisito no es válido.']);
            }
            if ($document && $document->procedure_request_id !== $requirement->dossier->titleProcess->procedure_request_id) {
                throw ValidationException::withMessages(['document' => 'El documento no pertenece al expediente.']);
            }
            $requirement->update(['status' => $status, 'request_document_id' => $document?->id, 'observation' => $observation, 'subsanated_at' => $status === 'subsanated' ? now() : $requirement->subsanated_at, 'verified_by' => $actor->id, 'verified_at' => $status === 'verified' ? now() : null]);
            $this->event($requirement->dossier, $actor, 'requirement_reviewed', 'Se revisó un requisito del expediente final.', ['code' => $requirement->code, 'status' => $status]);
        });
    }

    public function conform(TitleFinalDossier $dossier, User $actor, ?string $observations = null): void
    {
        DB::transaction(function () use ($dossier, $actor, $observations) {
            if ($dossier->requirements()->where(function ($q) {
                $q->where('status', '!=', 'verified')->where(function ($q) {
                    $q->where('conditional', false)->orWhere('status', '!=', 'not_applicable');
                });
            })->exists()) {
                throw ValidationException::withMessages(['requirements' => 'Existen requisitos obligatorios sin conformidad.']);
            }
            $dossier->update(['status' => 'conforming', 'observations' => $observations]);
            $this->event($dossier, $actor, 'conformed', 'El expediente final quedó conforme.', []);
        });
    }

    public function submitForRegistration(TitleFinalDossier $dossier, User $actor, mixed $date = null): void
    {
        if ($dossier->status !== 'conforming') {
            throw ValidationException::withMessages(['status' => 'El expediente debe estar conforme antes del envío.']);
        }
        DB::transaction(function () use ($dossier, $actor, $date) {
            $dossier->update(['status' => 'submitted', 'submitted_at' => $date ?? now()]);
            $this->event($dossier, $actor, 'submitted', 'El expediente fue enviado para registro.', []);
        });
    }

    public function register(TitleFinalDossier $dossier, User $actor, string $code, string $date): void
    {
        if ($dossier->status !== 'submitted') {
            throw ValidationException::withMessages(['status' => 'El expediente aún no fue enviado.']);
        }
        DB::transaction(function () use ($dossier, $actor, $code, $date) {
            $dossier->update(['status' => 'registered', 'registration_code' => $code, 'registered_at' => $date]);
            $this->event($dossier, $actor, 'registered', 'Se registró el título ante la entidad correspondiente.', ['registration_code' => $code]);
        });
    }

    public function markReady(TitleFinalDossier $dossier, User $actor, string $issuedAt, string $pickupAt): void
    {
        if ($dossier->status !== 'registered') {
            throw ValidationException::withMessages(['status' => 'El título todavía no está registrado.']);
        }
        DB::transaction(function () use ($dossier, $actor, $issuedAt, $pickupAt) {
            $dossier->update(['status' => 'ready', 'issued_at' => $issuedAt, 'pickup_at' => $pickupAt]);
            $this->event($dossier, $actor, 'ready', 'El título está listo para recojo.', []);
        });
    }

    public function deliver(TitleFinalDossier $dossier, User $actor): void
    {
        if ($dossier->status !== 'ready') {
            throw ValidationException::withMessages(['status' => 'El título no está listo para entrega.']);
        }
        DB::transaction(function () use ($dossier, $actor) {
            $dossier->update(['status' => 'delivered', 'delivered_at' => now()]);
            $process = $dossier->titleProcess;
            if ($process->current_stage === TitleProcessStage::FINAL_FILE) {
                $this->titles->transition($process, $actor, TitleProcessStage::EXTERNAL_REGISTRATION, 'El título fue registrado.');
            } $process->refresh();
            if ($process->current_stage === TitleProcessStage::EXTERNAL_REGISTRATION) {
                $this->titles->transition($process, $actor, TitleProcessStage::READY_FOR_DELIVERY, 'El título quedó listo para entrega.');
            } $process->refresh();
            if ($process->current_stage === TitleProcessStage::READY_FOR_DELIVERY) {
                $this->titles->transition($process, $actor, TitleProcessStage::DELIVERED, 'Se registró la entrega final del título.');
            } $this->event($dossier, $actor, 'delivered', 'Se registró la entrega final del título.', []);
        });
    }

    private function event(TitleFinalDossier $dossier, User $actor, string $action, string $description, array $snapshot): void
    {
        $dossier->events()->create(compact('action', 'description', 'snapshot') + ['actor_id' => $actor->id, 'created_at' => now()]);
        $request = $dossier->titleProcess->procedureRequest;
        $request->histories()->create(['status_id' => $request->status_id, 'user_id' => $actor->id, 'action' => 'title_dossier_'.$action, 'description' => $description]);
        AuditLog::create(['user_id' => $actor->id, 'action' => 'title_dossier_'.$action, 'auditable_type' => $request->getMorphClass(), 'auditable_id' => $request->id, 'details' => $snapshot, 'ip_address' => request()->ip(), 'user_agent' => request()->userAgent()]);
    }
}
