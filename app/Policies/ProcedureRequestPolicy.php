<?php

namespace App\Policies;

use App\Models\ProcedureRequest;
use App\Models\User;

class ProcedureRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isApplicant($user);
    }

    public function create(User $user): bool
    {
        return $this->isApplicant($user);
    }

    public function view(User $user, ProcedureRequest $procedureRequest): bool
    {
        return $this->isApplicant($user) && $procedureRequest->user_id === $user->id;
    }

    public function download(User $user, ProcedureRequest $procedureRequest): bool
    {
        return $this->view($user, $procedureRequest);
    }

    public function downloadResponse(User $user, ProcedureRequest $procedureRequest): bool
    {
        return $this->view($user, $procedureRequest) && $procedureRequest->response()->exists();
    }

    public function reviewAny(User $user): bool
    {
        return $this->isFrontDesk($user);
    }

    public function review(User $user, ProcedureRequest $procedureRequest): bool
    {
        return $this->isFrontDesk($user);
    }

    public function reviewDownload(User $user, ProcedureRequest $procedureRequest): bool
    {
        return $this->review($user, $procedureRequest);
    }

    public function startReview(User $user, ProcedureRequest $procedureRequest): bool
    {
        return $this->isFrontDesk($user);
    }

    public function confirmPhysicalReception(User $user, ProcedureRequest $procedureRequest): bool
    {
        return $this->isFrontDesk($user);
    }

    public function createPhysical(User $user): bool
    {
        return $user->active && $user->role?->active && in_array($user->role?->name, ['Mesa de Partes', 'Administrador'], true);
    }

    public function printPhysicalReceipt(User $user, ProcedureRequest $procedureRequest): bool
    {
        return $this->createPhysical($user) && $procedureRequest->physicalReception()->exists();
    }

    public function manageCommunications(User $user, ProcedureRequest $procedureRequest): bool
    {
        if (! $user->active || ! $user->role?->active) {
            return false;
        }
        if (in_array($user->role->name, ['Mesa de Partes', 'Administrador'], true)) {
            return true;
        }

        return $user->role->name === 'Responsable de área' && $user->area_id !== null
            && $procedureRequest->latestDerivation()->where('to_area_id', $user->area_id)->exists();
    }

    public function validateReview(User $user, ProcedureRequest $procedureRequest): bool
    {
        return $this->isFrontDesk($user);
    }

    public function observe(User $user, ProcedureRequest $procedureRequest): bool
    {
        return $this->isFrontDesk($user);
    }

    public function reject(User $user, ProcedureRequest $procedureRequest): bool
    {
        return $this->isFrontDesk($user);
    }

    public function deriveAny(User $user): bool
    {
        return $this->isFrontDesk($user);
    }

    public function derive(User $user, ProcedureRequest $procedureRequest): bool
    {
        return $this->isFrontDesk($user);
    }

    public function viewAssignedAny(User $user): bool
    {
        return $this->isAreaManager($user) && $user->area_id !== null;
    }

    public function viewAssigned(User $user, ProcedureRequest $procedureRequest): bool
    {
        return $this->viewAssignedAny($user)
            && $procedureRequest->latestDerivation()->where('to_area_id', $user->area_id)->exists();
    }

    public function receiveAssigned(User $user, ProcedureRequest $procedureRequest): bool
    {
        return $this->viewAssigned($user, $procedureRequest);
    }

    public function attendAssigned(User $user, ProcedureRequest $procedureRequest): bool
    {
        return $this->viewAssigned($user, $procedureRequest);
    }

    public function closeAny(User $user): bool
    {
        return $this->isFrontDesk($user);
    }

    public function close(User $user, ProcedureRequest $procedureRequest): bool
    {
        return $this->isFrontDesk($user);
    }

    public function reviewResponseDownload(User $user, ProcedureRequest $procedureRequest): bool
    {
        return $this->close($user, $procedureRequest) && $procedureRequest->response()->exists();
    }

    public function correct(User $user, ProcedureRequest $procedureRequest): bool
    {
        return $this->view($user, $procedureRequest)
            && $procedureRequest->status()->value('code') === 'OBSERVADO'
            && $procedureRequest->observations()->whereNull('resolved_at')->exists();
    }

    private function isApplicant(User $user): bool
    {
        return $user->active
            && $user->role?->active
            && $user->role?->name === 'Solicitante';
    }

    private function isFrontDesk(User $user): bool
    {
        return $user->active
            && $user->role?->active
            && $user->role?->name === 'Mesa de Partes';
    }

    private function isAreaManager(User $user): bool
    {
        return $user->active
            && $user->role?->active
            && $user->role?->name === 'Responsable de área';
    }
}
