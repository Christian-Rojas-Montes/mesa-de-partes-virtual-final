<?php

namespace App\Policies;

use App\Models\ProcedureRequirement;
use App\Models\User;
use App\Policies\Concerns\AuthorizesAdministrators;

class ProcedureRequirementPolicy
{
    use AuthorizesAdministrators;

    public function viewAny(User $user): bool
    {
        return $this->isAdministrator($user);
    }

    public function view(User $user, ProcedureRequirement $requirement): bool
    {
        return $this->isAdministrator($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdministrator($user);
    }

    public function update(User $user, ProcedureRequirement $requirement): bool
    {
        return $this->isAdministrator($user);
    }

    public function toggle(User $user, ProcedureRequirement $requirement): bool
    {
        return $this->isAdministrator($user);
    }
}
