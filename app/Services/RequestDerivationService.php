<?php

namespace App\Services;

use App\Models\Area;
use App\Models\AuditLog;
use App\Models\ProcedureRequest;
use App\Models\ProcedureType;
use App\Models\RequestDerivation;
use App\Models\Status;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequestDerivationService
{
    public function __construct(private readonly InternalNotificationService $notifications) {}

    public function derive(ProcedureRequest $procedureRequest, Area $area, User $actor, ?string $reason): RequestDerivation
    {
        return DB::transaction(function () use ($procedureRequest, $area, $actor, $reason) {
            $locked = ProcedureRequest::query()->lockForUpdate()->findOrFail($procedureRequest->id);
            $procedureType = ProcedureType::query()->lockForUpdate()->findOrFail($locked->procedure_type_id);
            $areaIds = collect([$area->id, $procedureType->responsible_area_id])
                ->filter()
                ->unique()
                ->sortBy(fn ($id) => (int) $id)
                ->values();
            $lockedAreas = Area::query()
                ->whereKey($areaIds->all())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $destination = $lockedAreas->get($area->id);

            if ($destination === null) {
                abort(404);
            }

            $statusCode = $locked->status()->value('code');
            $latest = $locked->derivations()->lockForUpdate()->latest('derived_at')->latest('id')->first();

            if (! $destination->active) {
                $this->invalid('area_id', 'El área seleccionada no está activa.');
            }

            if ($procedureType->responsible_area_id !== null) {
                $configuredArea = $lockedAreas->get($procedureType->responsible_area_id);

                if ($configuredArea === null || ! $configuredArea->active) {
                    $this->invalid('area_id', 'El área responsable configurada para este trámite no está disponible. Solicita que se actualice el catálogo.');
                }

                if ($destination->id !== $configuredArea->id) {
                    $this->invalid('area_id', 'Este trámite debe derivarse al área responsable configurada: '.$configuredArea->name.'.');
                }
            }

            $isInitial = $statusCode === 'EN_REVISION' && $locked->validated_at !== null && $latest === null;
            $isCorrection = $statusCode === 'DERIVADO' && $latest !== null;

            if (! $isInitial && ! $isCorrection) {
                $this->invalid('action', 'Solo se pueden derivar expedientes validados o corregir una derivación vigente.');
            }

            if ($isCorrection && $latest->received_at !== null) {
                $this->invalid('action', 'No se puede corregir la derivación porque el área destinataria ya registró la recepción.');
            }

            if ($latest?->to_area_id === $destination->id) {
                $this->invalid('area_id', 'El expediente ya se encuentra derivado a esa área.');
            }

            $derivedStatus = Status::active()->where('code', 'DERIVADO')->firstOrFail();
            $derivation = $locked->derivations()->create([
                'from_area_id' => $latest?->to_area_id,
                'to_area_id' => $destination->id,
                'user_id' => $actor->id,
                'reason' => $reason,
                'derived_at' => now(),
            ]);
            $locked->update(['status_id' => $derivedStatus->id]);

            $action = $isInitial ? 'derived' : 'rederived';
            $description = ($isInitial ? 'El expediente fue derivado a ' : 'La derivación fue corregida y el expediente fue enviado a ')
                .$destination->name.'.'.($reason ? ' Motivo: '.$reason : '');

            $locked->histories()->create([
                'status_id' => $derivedStatus->id,
                'user_id' => $actor->id,
                'action' => $action,
                'description' => $description,
            ]);
            $this->audit($locked, $actor, $action, [
                'derivation_id' => $derivation->id,
                'from_area_id' => $latest?->to_area_id,
                'to_area_id' => $destination->id,
            ]);

            $this->notifications->dispatch(
                $locked,
                InternalNotificationService::DERIVED,
                'Tu expediente fue derivado a '.$destination->name.'.',
                (string) $derivation->id,
                $destination->id,
                'Se asignó el expediente '.$locked->tracking_code.' a tu área.',
                previousAreaId: $latest?->to_area_id,
            );

            return $derivation->load(['originArea', 'destinationArea', 'responsible']);
        });
    }

    public function receive(ProcedureRequest $procedureRequest, RequestDerivation $derivation, User $actor): RequestDerivation
    {
        return DB::transaction(function () use ($procedureRequest, $derivation, $actor) {
            $locked = ProcedureRequest::query()->lockForUpdate()->findOrFail($procedureRequest->id);
            $lockedDerivation = RequestDerivation::query()->lockForUpdate()->findOrFail($derivation->id);
            $latestId = $locked->derivations()->latest('derived_at')->latest('id')->value('id');

            if ($lockedDerivation->procedure_request_id !== $locked->id || $latestId !== $lockedDerivation->id) {
                $this->invalid('action', 'Solo puede recibirse la derivación vigente del expediente.');
            }

            if ($actor->area_id === null || $lockedDerivation->to_area_id !== $actor->area_id) {
                abort(403);
            }

            if ($locked->status()->value('code') !== 'DERIVADO') {
                $this->invalid('action', 'Solo puede recibirse un expediente que se encuentre en estado Derivado.');
            }

            if ($lockedDerivation->received_at !== null) {
                $this->invalid('action', 'La recepción de esta derivación ya fue registrada.');
            }

            $lockedDerivation->update(['received_at' => now()]);
            $areaName = $lockedDerivation->destinationArea()->value('name');
            $locked->histories()->create([
                'status_id' => $locked->status_id,
                'user_id' => $actor->id,
                'action' => 'derivation_received',
                'description' => 'El área '.$areaName.' registró la recepción del expediente.',
            ]);
            $this->audit($locked, $actor, 'derivation_received', [
                'derivation_id' => $lockedDerivation->id,
                'area_id' => $lockedDerivation->to_area_id,
            ]);
            $this->notifications->dispatch(
                $locked,
                InternalNotificationService::RECEIVED,
                'El área '.$areaName.' recibió tu expediente.',
                (string) $lockedDerivation->id,
            );

            return $lockedDerivation->refresh();
        });
    }

    /** @param array<string, mixed> $details */
    private function audit(ProcedureRequest $procedureRequest, User $actor, string $action, array $details): void
    {
        AuditLog::query()->create([
            'user_id' => $actor->id,
            'action' => $action,
            'auditable_type' => $procedureRequest->getMorphClass(),
            'auditable_id' => $procedureRequest->id,
            'details' => $details,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
