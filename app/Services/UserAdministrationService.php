<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class UserAdministrationService
{
    /** @param array<string, mixed> $attributes */
    public function createInternalUser(array $attributes): User
    {
        return DB::transaction(fn () => User::query()->create([
            ...$attributes,
            'password' => Str::random(64),
            'active' => true,
        ]));
    }

    public function toggleActive(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->forceFill([
                'active' => ! $user->active,
                'remember_token' => Str::random(60),
            ])->save();

            if (! $user->active) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
        });
    }

    public function sendAccessReset(User $user): string
    {
        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status === Password::RESET_LINK_SENT) {
            AuditLog::query()->create([
                'user_id' => auth()->id(),
                'action' => 'access_reset_requested',
                'auditable_type' => $user->getMorphClass(),
                'auditable_id' => $user->id,
                'details' => ['channel' => 'email', 'temporary_link' => true],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        }

        return $status;
    }
}
