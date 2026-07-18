<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ProcedureRequest;
use App\Models\RequestPhysicalReception;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PhysicalReceptionService
{
    public function __construct(private readonly InternalNotificationService $notifications) {}

    public function confirm(ProcedureRequest $procedureRequest, User $actor, array $data): RequestPhysicalReception
    {
        return DB::transaction(function () use ($procedureRequest, $actor, $data) {
            $locked = ProcedureRequest::query()->lockForUpdate()->with(['status', 'physicalReception'])->findOrFail($procedureRequest->id);
            if ($locked->physicalReception) {
                throw ValidationException::withMessages(['received_at' => 'La recepción física ya fue confirmada.']);
            }
            $modality = data_get($locked->configuration_snapshot, 'modality_code');
            $required = data_get($locked->configuration_snapshot, 'physical_submission.required', false) || data_get($locked->configuration_snapshot, 'submission_channel') === 'in_person';
            if (! in_array($modality, ['hybrid', 'in_person'], true) && ! $required) {
                throw ValidationException::withMessages(['received_at' => 'Esta solicitud no requiere recepción física.']);
            }

            $reception = $locked->physicalReception()->create([...$data, 'received_by' => $actor->id]);
            $this->milestone($locked, $actor, 'physical_documents_received', 'Mesa de Partes confirmó la recepción de la documentación física.');
            if ($data['verification_result'] === 'incomplete') {
                $this->milestone($locked, $actor, 'physical_documents_incomplete', 'La documentación física fue recibida con observaciones de integridad.');
            }
            if ($data['verification_result'] === 'originals_verified') {
                $this->milestone($locked, $actor, 'originals_verified', 'Mesa de Partes verificó los documentos originales.');
            }

            AuditLog::query()->create(['user_id' => $actor->id, 'action' => 'physical_reception_confirmed', 'auditable_type' => $locked->getMorphClass(), 'auditable_id' => $locked->id, 'details' => ['physical_reception_id' => $reception->id, 'document_count' => $reception->document_count, 'verification_result' => $reception->verification_result], 'ip_address' => request()->ip(), 'user_agent' => request()->userAgent()]);
            $this->notifications->dispatch($locked, 'physical_reception_confirmed', 'Mesa de Partes confirmó la recepción física de tu trámite.', 'physical-reception');

            return $reception;
        });
    }

    public function milestone(ProcedureRequest $request, User $actor, string $action, string $description): void
    {
        $allowed = ['pre_registration_created', 'pending_physical_delivery', 'physical_documents_received', 'physical_documents_incomplete', 'originals_verified', 'ready_for_pickup', 'delivered'];
        abort_unless(in_array($action, $allowed, true), 422);
        $request->histories()->create(['status_id' => $request->status_id, 'user_id' => $actor->id, 'action' => $action, 'description' => $description]);
    }
}
