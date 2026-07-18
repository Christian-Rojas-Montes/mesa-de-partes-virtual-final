<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait AuthorizesAdministrators
{
    private function isAdministrator(User $user): bool
    {
        return $user->active
            && $user->role?->active
            && $user->role?->name === 'Administrador';
    }
}
