<?php

namespace App\Policies;

use App\Models\Area;
use App\Models\User;
use App\Policies\Concerns\AuthorizesAdministrators;

class AreaPolicy
{
    use AuthorizesAdministrators;

    public function viewAny(User $user): bool
    {
        return $this->isAdministrator($user);
    }

    public function view(User $user, Area $area): bool
    {
        return $this->isAdministrator($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdministrator($user);
    }

    public function update(User $user, Area $area): bool
    {
        return $this->isAdministrator($user);
    }

    public function toggle(User $user, Area $area): bool
    {
        return $this->isAdministrator($user);
    }
}
