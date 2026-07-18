<?php

namespace App\Services;

use App\Models\ProcedureRequest;
use App\Models\User;

class NotificationDestinationResolver
{
    public function route(User $user, ProcedureRequest $procedureRequest): string
    {
        if ($user->can('view', $procedureRequest)) {
            return route('applicant.procedure-requests.show', $procedureRequest);
        }

        if ($user->can('viewAssigned', $procedureRequest)) {
            return route('area-manager.assignments.show', $procedureRequest);
        }

        if ($user->can('close', $procedureRequest)) {
            $statusCode = $procedureRequest->status()->value('code');

            return match ($statusCode) {
                'ATENDIDO', 'FINALIZADO' => route('front-desk.closures.show', $procedureRequest),
                'DERIVADO' => route('front-desk.derivations.create', $procedureRequest),
                default => route('front-desk.reviews.show', $procedureRequest),
            };
        }

        return route('dashboard');
    }
}
