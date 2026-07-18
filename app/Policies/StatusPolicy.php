<?php

namespace App\Policies;

use App\Models\Status;
use App\Models\User;
use App\Policies\Concerns\AuthorizesAdministrators;

class StatusPolicy
{
    use AuthorizesAdministrators;

    public function viewAny(User $user): bool
    {
        return $this->isAdministrator($user);
    }

    public function view(User $user, Status $status): bool
    {
        return $this->isAdministrator($user);
    }
}
