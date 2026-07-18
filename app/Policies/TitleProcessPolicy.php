<?php

namespace App\Policies;

use App\Models\TitleProcess;
use App\Models\User;

class TitleProcessPolicy
{
    public function view(User $user, TitleProcess $process): bool
    {
        return $user->active && $user->role?->active && ($process->procedureRequest->user_id === $user->id
            || in_array($user->role?->name, ['Mesa de Partes', 'Administrador', 'Responsable de área'], true));
    }

    public function manage(User $user, TitleProcess $process): bool
    {
        return $user->active && $user->role?->active
            && in_array($user->role?->name, ['Mesa de Partes', 'Administrador', 'Responsable de área'], true);
    }

    public function createApplicationWork(User $user, TitleProcess $process): bool
    {
        return $this->view($user, $process) && $process->procedureRequest->user_id === $user->id
            && $process->modality->value === 'application_work' && ! $process->applicationWorkProject()->exists();
    }

    public function createProfessionalExam(User $user, TitleProcess $process): bool
    {
        return $this->view($user, $process) && $process->procedureRequest->user_id === $user->id
            && $process->modality->value === 'professional_exam' && ! $process->professionalExamProfile()->exists();
    }
}
