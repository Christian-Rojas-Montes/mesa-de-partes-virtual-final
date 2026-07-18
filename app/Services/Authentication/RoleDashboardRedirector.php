<?php

namespace App\Services\Authentication;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class RoleDashboardRedirector
{
    public function routeName(User $user): string
    {
        return match ($user->role?->name) {
            'Solicitante' => 'dashboard.applicant',
            'Mesa de Partes' => 'dashboard.front-desk',
            'Responsable de área' => 'dashboard.area-manager',
            'Administrador' => 'dashboard.administrator',
            default => throw new AuthorizationException('El usuario no tiene un rol válido.'),
        };
    }
}
