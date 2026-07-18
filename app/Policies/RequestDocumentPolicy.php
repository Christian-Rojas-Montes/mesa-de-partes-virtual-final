<?php

namespace App\Policies;

use App\Models\RequestDocument;
use App\Models\User;

class RequestDocumentPolicy
{
    public function download(User $user, RequestDocument $document): bool
    {
        if (! $user->active || ! $user->role?->active || $document->disk !== 'private') {
            return false;
        }

        $request = $document->procedureRequest;

        return match ($user->role->name) {
            'Solicitante' => $request->user_id === $user->id,
            'Mesa de Partes', 'Administrador' => true,
            'Responsable de área', 'Personal académico' => $user->area_id !== null
                && $request->latestDerivation()->where('to_area_id', $user->area_id)->exists(),
            default => false,
        };
    }
}
