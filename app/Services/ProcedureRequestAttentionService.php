<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ProcedureRequest;
use App\Models\RequestAttentionAction;
use App\Models\RequestResponse;
use App\Models\Status;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProcedureRequestAttentionService
{
    public function __construct(
        private readonly PrivateDocumentStorage $documentStorage,
        private readonly InternalNotificationService $notifications,
    ) {}

    public function start(ProcedureRequest $procedureRequest, User $actor): ProcedureRequest
    {
        return DB::transaction(function () use ($procedureRequest, $actor) {
            $locked = $this->lockedRequest($procedureRequest);
            $derivation = $this->assertAssigned($locked, $actor);
            $this->assertStatus($locked, 'DERIVADO');

            if ($derivation->received_at === null) {
                $this->invalid('action', 'Debes registrar la recepción del expediente antes de iniciar su atención.');
            }

            $status = $this->status('EN_ATENCION');
            $locked->update(['status_id' => $status->id]);
            $this->history($locked, $actor, 'attention_started', 'El área responsable inició la atención del expediente.');
            $this->audit($locked, $actor, 'attention_started');
            $this->notify($locked, InternalNotificationService::ATTENTION_STARTED, 'El área responsable inició la atención de tu expediente.', 'start');

            return $locked;
        });
    }

    public function recordAction(ProcedureRequest $procedureRequest, User $actor, string $description): RequestAttentionAction
    {
        return DB::transaction(function () use ($procedureRequest, $actor, $description) {
            $locked = $this->lockedRequest($procedureRequest);
            $this->assertAssigned($locked, $actor);
            $this->assertStatus($locked, 'EN_ATENCION');

            $action = $locked->attentionActions()->create([
                'user_id' => $actor->id,
                'description' => $description,
            ]);
            $this->history($locked, $actor, 'attention_action', 'Acción de atención registrada: '.$description);
            $this->audit($locked, $actor, 'attention_action', ['attention_action_id' => $action->id]);
            $this->notify($locked, InternalNotificationService::ATTENTION_ACTION, 'Se registró un avance en la atención de tu expediente.', (string) $action->id);

            return $action;
        });
    }

    public function respond(
        ProcedureRequest $procedureRequest,
        User $actor,
        string $summary,
        UploadedFile $document,
    ): RequestResponse {
        $storedPath = null;

        try {
            return DB::transaction(function () use ($procedureRequest, $actor, $summary, $document, &$storedPath) {
                $locked = $this->lockedRequest($procedureRequest);
                $this->assertAssigned($locked, $actor);
                $this->assertStatus($locked, 'EN_ATENCION');

                if ($locked->response()->exists()) {
                    $this->invalid('action', 'La solicitud ya cuenta con una respuesta final.');
                }

                $metadata = $this->documentStorage->storeResponse($document, $locked);
                $storedPath = $metadata['path'];
                $response = $locked->response()->create([
                    'user_id' => $actor->id,
                    'summary' => $summary,
                    'responded_at' => now(),
                    ...$metadata,
                ]);

                $status = $this->status('ATENDIDO');
                $locked->update(['status_id' => $status->id]);
                $this->history($locked, $actor, 'response_registered', 'El área responsable registró la respuesta final del expediente.');
                $this->audit($locked, $actor, 'response_registered', ['response_id' => $response->id]);
                $this->notify($locked, InternalNotificationService::RESPONSE_REGISTERED, 'Tu expediente fue atendido y ya cuenta con una respuesta disponible.', (string) $response->id);

                return $response;
            });
        } catch (Throwable $exception) {
            if ($storedPath !== null) {
                $this->documentStorage->delete([$storedPath]);
            }

            throw $exception;
        }
    }

    public function finalize(ProcedureRequest $procedureRequest, User $actor): ProcedureRequest
    {
        return DB::transaction(function () use ($procedureRequest, $actor) {
            $locked = $this->lockedRequest($procedureRequest);
            $this->assertStatus($locked, 'ATENDIDO');

            if (! $locked->response()->exists()) {
                $this->invalid('action', 'No se puede finalizar un expediente sin respuesta.');
            }

            $status = $this->status('FINALIZADO');
            $locked->update(['status_id' => $status->id]);
            $this->history($locked, $actor, 'request_finalized', 'Mesa de Partes verificó la respuesta y cerró el expediente.');
            $this->audit($locked, $actor, 'request_finalized');
            $this->notify($locked, InternalNotificationService::FINALIZED, 'Tu expediente fue finalizado. Puedes consultar y descargar la respuesta registrada.', 'finalization');

            return $locked;
        });
    }

    private function lockedRequest(ProcedureRequest $procedureRequest): ProcedureRequest
    {
        return ProcedureRequest::query()->lockForUpdate()->findOrFail($procedureRequest->id);
    }

    private function assertAssigned(ProcedureRequest $procedureRequest, User $actor): \App\Models\RequestDerivation
    {
        $derivation = $procedureRequest->derivations()->lockForUpdate()->latest('derived_at')->latest('id')->first();

        if ($actor->area_id === null || $derivation?->to_area_id !== $actor->area_id) {
            abort(403);
        }

        return $derivation;
    }

    private function assertStatus(ProcedureRequest $procedureRequest, string $code): void
    {
        if ($procedureRequest->status()->value('code') !== $code) {
            $this->invalid('action', 'La acción no está permitida para el estado actual del expediente.');
        }
    }

    private function status(string $code): Status
    {
        return Status::active()->where('code', $code)->firstOrFail();
    }

    private function history(ProcedureRequest $procedureRequest, User $actor, string $action, string $description): void
    {
        $procedureRequest->histories()->create([
            'status_id' => $procedureRequest->status_id,
            'user_id' => $actor->id,
            'action' => $action,
            'description' => $description,
        ]);
    }

    /** @param array<string, mixed> $details */
    private function audit(ProcedureRequest $procedureRequest, User $actor, string $action, array $details = []): void
    {
        AuditLog::query()->create([
            'user_id' => $actor->id,
            'action' => $action,
            'auditable_type' => $procedureRequest->getMorphClass(),
            'auditable_id' => $procedureRequest->id,
            'details' => ['status_id' => $procedureRequest->status_id, ...$details],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    private function notify(ProcedureRequest $procedureRequest, string $event, string $message, string $key): void
    {
        $this->notifications->dispatch($procedureRequest, $event, $message, $key);
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
