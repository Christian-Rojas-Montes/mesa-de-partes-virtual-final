<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\User;

class UserAuditObserver
{
    /** @var list<string> */
    private const AUDITABLE_FIELDS = [
        'role_id',
        'area_id',
        'document_type',
        'document_number',
        'first_name',
        'last_name',
        'email',
        'phone',
        'active',
    ];

    public function created(User $user): void
    {
        $this->record($user, 'created', $user->only(self::AUDITABLE_FIELDS));
    }

    public function updated(User $user): void
    {
        $changes = array_intersect_key($user->getChanges(), array_flip(self::AUDITABLE_FIELDS));

        if ($changes === []) {
            return;
        }

        $action = match (true) {
            $user->wasChanged('active') && $user->active => 'activated',
            $user->wasChanged('active') => 'deactivated',
            default => 'updated',
        };

        $this->record($user, $action, $changes);
    }

    /** @param array<string, mixed> $changes */
    private function record(User $target, string $action, array $changes): void
    {
        $actor = auth()->user();

        if ($actor?->role?->name !== 'Administrador') {
            return;
        }

        AuditLog::query()->create([
            'user_id' => $actor->id,
            'action' => $action,
            'auditable_type' => $target->getMorphClass(),
            'auditable_id' => $target->id,
            'details' => ['changes' => $changes],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
