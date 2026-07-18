<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AuthorizesAdministrators;
use Illuminate\Database\Eloquent\Model;

class ConfigurableCatalogPolicy
{
    use AuthorizesAdministrators;

    public function viewAny(User $user): bool
    {
        return $this->isAdministrator($user);
    }

    public function view(User $user, Model $model): bool
    {
        return $this->isAdministrator($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdministrator($user);
    }

    public function update(User $user, Model $model): bool
    {
        return $this->isAdministrator($user);
    }

    public function toggle(User $user, Model $model): bool
    {
        return $this->isAdministrator($user);
    }
}
