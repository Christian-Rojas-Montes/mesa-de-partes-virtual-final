<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ProcedureRequest;
use App\Models\RequestObservation;
use App\Models\Status;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProcedureRequestTransitionService
{
    public function __construct(
        private readonly PrivateDocumentStorage $documentStorage,
        private readonly InternalNotificationService $notifications,
    ) {}

    public function startReview(ProcedureRequest $procedureRequest, User $actor): ProcedureRequest
    {
        return $this->perform($procedureRequest, function (ProcedureRequest $locked) use ($actor) {
            $this->assertStatus($locked, 'REGISTRADO');

            return $this->transition(
                $locked,
                $actor,
                'EN_REVISION',
                'review_started',
                'La revisión de la solicitud fue iniciada.',
                'Tu solicitud se encuentra en revisión.',
                InternalNotificationService::REVIEW_STARTED,
                'review-start',
            );
        });
    }

    public function validate(ProcedureRequest $procedureRequest, User $actor): ProcedureRequest
    {
        return $this->perform($procedureRequest, function (ProcedureRequest $locked) use ($actor) {
            $this->assertStatus($locked, 'EN_REVISION');

            if ($locked->validated_at !== null) {
                $this->invalid('La solicitud ya fue validada.');
            }

            $locked->update(['validated_by' => $actor->id, 'validated_at' => now()]);
            $this->recordHistory($locked, $actor, 'validated', 'La documentación fue validada por Mesa de Partes.');
            $this->recordAudit($locked, $actor, 'validated');
            $this->notify(
                $locked,
                InternalNotificationService::VALIDATED,
                'La documentación de tu solicitud fue validada.',
                'validation',
            );

            return $locked;
        });
    }

    /** @param array{description: string, correction_instructions?: string|null, correction_deadline?: string|null} $data */
    public function observe(ProcedureRequest $procedureRequest, User $actor, array $data): ProcedureRequest
    {
        return $this->perform($procedureRequest, function (ProcedureRequest $locked) use ($actor, $data) {
            $this->assertStatus($locked, 'EN_REVISION');
            $observation = $locked->observations()->create([
                'user_id' => $actor->id,
                'description' => $data['description'],
                'correction_instructions' => $data['correction_instructions'] ?? null,
                'correction_deadline' => $data['correction_deadline'] ?? null,
            ]);
            $locked->update(['validated_by' => null, 'validated_at' => null]);

            return $this->transition(
                $locked,
                $actor,
                'OBSERVADO',
                'observed',
                'La solicitud fue observada. Motivo: '.$data['description'],
                'Tu solicitud tiene una observación pendiente de subsanación.',
                InternalNotificationService::OBSERVED,
                (string) $observation->id,
            );
        });
    }

    public function reject(ProcedureRequest $procedureRequest, User $actor, string $reason): ProcedureRequest
    {
        return $this->perform($procedureRequest, function (ProcedureRequest $locked) use ($actor, $reason) {
            $this->assertStatus($locked, 'EN_REVISION');
            $rejection = $locked->rejection()->create(['user_id' => $actor->id, 'reason' => $reason]);

            return $this->transition(
                $locked,
                $actor,
                'RECHAZADO',
                'rejected',
                'La solicitud fue rechazada. Motivo: '.$reason,
                'Tu solicitud fue rechazada. Revisa el motivo en el seguimiento.',
                InternalNotificationService::REJECTED,
                (string) $rejection->id,
            );
        });
    }

    /**
     * @param  array<int|string, UploadedFile>  $files
     */
    public function submitCorrection(
        ProcedureRequest $procedureRequest,
        RequestObservation $observation,
        User $applicant,
        array $files,
        ?string $message,
    ): ProcedureRequest {
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($procedureRequest, $observation, $applicant, $files, $message, &$storedPaths) {
                $locked = ProcedureRequest::query()->lockForUpdate()->findOrFail($procedureRequest->id);
                $lockedObservation = RequestObservation::query()->lockForUpdate()->findOrFail($observation->id);
                $this->assertStatus($locked, 'OBSERVADO');

                if ($lockedObservation->procedure_request_id !== $locked->id || $lockedObservation->resolved_at !== null) {
                    $this->invalid('La observación ya no admite una subsanación.');
                }

                $correction = $locked->corrections()->create([
                    'request_observation_id' => $lockedObservation->id,
                    'user_id' => $applicant->id,
                    'message' => $message,
                    'submitted_at' => now(),
                ]);

                foreach ($files as $requirementId => $file) {
                    $metadata = $this->documentStorage->store($file, $locked);
                    $storedPaths[] = $metadata['path'];
                    $locked->documents()->create([
                        'request_correction_id' => $correction->id,
                        'procedure_requirement_id' => is_numeric($requirementId) ? (int) $requirementId : null,
                        ...$metadata,
                    ]);
                }

                $lockedObservation->update(['resolved_at' => now()]);
                $locked->update(['validated_by' => null, 'validated_at' => null]);

                return $this->transition(
                    $locked,
                    $applicant,
                    'EN_REVISION',
                    'correction_submitted',
                    'El solicitante presentó documentos de subsanación.',
                    'Tu subsanación fue registrada y el expediente retornó a revisión.',
                    InternalNotificationService::CORRECTION_SUBMITTED,
                    (string) $correction->id,
                );
            });
        } catch (Throwable $exception) {
            $this->documentStorage->delete($storedPaths);
            throw $exception;
        }
    }

    /** @param callable(ProcedureRequest): ProcedureRequest $callback */
    private function perform(ProcedureRequest $procedureRequest, callable $callback): ProcedureRequest
    {
        return DB::transaction(fn () => $callback(
            ProcedureRequest::query()->lockForUpdate()->findOrFail($procedureRequest->id),
        ));
    }

    private function transition(
        ProcedureRequest $procedureRequest,
        User $actor,
        string $targetCode,
        string $action,
        string $historyDescription,
        string $notificationMessage,
        string $notificationEvent,
        string $notificationKey,
    ): ProcedureRequest {
        $targetStatus = Status::active()->where('code', $targetCode)->firstOrFail();
        $procedureRequest->update(['status_id' => $targetStatus->id]);
        $procedureRequest->setRelation('status', $targetStatus);
        $this->recordHistory($procedureRequest, $actor, $action, $historyDescription);
        $this->recordAudit($procedureRequest, $actor, $action);
        $this->notify($procedureRequest, $notificationEvent, $notificationMessage, $notificationKey);

        return $procedureRequest;
    }

    private function assertStatus(ProcedureRequest $procedureRequest, string $expectedCode): void
    {
        if ($procedureRequest->status()->value('code') !== $expectedCode) {
            $this->invalid('La acción no está permitida para el estado actual de la solicitud.');
        }
    }

    private function recordHistory(ProcedureRequest $procedureRequest, User $actor, string $action, string $description): void
    {
        $procedureRequest->histories()->create([
            'status_id' => $procedureRequest->status_id,
            'user_id' => $actor->id,
            'action' => $action,
            'description' => $description,
        ]);
    }

    private function recordAudit(ProcedureRequest $procedureRequest, User $actor, string $action): void
    {
        AuditLog::query()->create([
            'user_id' => $actor->id,
            'action' => $action,
            'auditable_type' => $procedureRequest->getMorphClass(),
            'auditable_id' => $procedureRequest->id,
            'details' => ['status_id' => $procedureRequest->status_id],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    private function notify(ProcedureRequest $procedureRequest, string $event, string $message, string $key): void
    {
        $this->notifications->dispatch($procedureRequest, $event, $message, $key);
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['action' => $message]);
    }
}
