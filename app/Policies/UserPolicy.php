<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AuthorizesAdministrators;

class UserPolicy
{
    use AuthorizesAdministrators;

    public function viewAny(User $user): bool
    {
        return $this->isAdministrator($user);
    }

    public function view(User $user, User $target): bool
    {
        return $this->isAdministrator($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdministrator($user);
    }

    public function update(User $user, User $target): bool
    {
        return $this->isAdministrator($user);
    }

    public function toggle(User $user, User $target): bool
    {
        return $this->isAdministrator($user) && $user->isNot($target);
    }

    public function resetAccess(User $user, User $target): bool
    {
        return $this->isAdministrator($user) && $target->active;
    }
}
