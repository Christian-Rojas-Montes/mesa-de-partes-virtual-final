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
            'active' => true,
        ]));
    }

    /** @param array<string, mixed> $attributes */
    public function updateInternalUser(User $user, array $attributes): User
    {
        return DB::transaction(function () use ($user, $attributes): User {
            $account = User::query()->lockForUpdate()->findOrFail($user->id);
            $password = $attributes['password'] ?? null;
            unset($attributes['password']);

            $account->fill($attributes);

            if (filled($password)) {
                $account->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ]);
            }

            $account->save();

            if (filled($password)) {
                DB::table('sessions')
                    ->where('user_id', $account->id)
                    ->where('id', '!=', session()->getId())
                    ->delete();
            }

            return $account;
        });
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
