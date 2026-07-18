<?php

namespace App\Policies;

use App\Models\ProcedureType;
use App\Models\User;
use App\Policies\Concerns\AuthorizesAdministrators;

class ProcedureTypePolicy
{
    use AuthorizesAdministrators;

    public function viewAny(User $user): bool
    {
        return $this->isAdministrator($user);
    }

    public function view(User $user, ProcedureType $procedureType): bool
    {
        return $this->isAdministrator($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdministrator($user);
    }

    public function update(User $user, ProcedureType $procedureType): bool
    {
        return $this->isAdministrator($user);
    }

    public function toggle(User $user, ProcedureType $procedureType): bool
    {
        return $this->isAdministrator($user);
    }
}
