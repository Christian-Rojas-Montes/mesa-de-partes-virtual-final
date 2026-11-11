<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserProfileService
{
    public function updatePhone(User $user, ?string $phone): bool
    {
        return DB::transaction(function () use ($user, $phone): bool {
            $account = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($account->phone === $phone) {
                return false;
            }

            $account->forceFill(['phone' => $phone])->save();
            $this->recordAudit($account, 'profile_phone_updated', ['field' => 'phone']);

            return true;
        });
    }

    public function updatePassword(User $user, string $password): void
    {
        DB::transaction(function () use ($user, $password): void {
            $account = User::query()->lockForUpdate()->findOrFail($user->id);
            $account->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            DB::table('sessions')
                ->where('user_id', $account->id)
                ->where('id', '!=', session()->getId())
                ->delete();

            $this->recordAudit($account, 'profile_password_changed', ['field' => 'password']);
        });
    }

    /** @param array<string, mixed> $details */
    private function recordAudit(User $user, string $action, array $details): void
    {
        AuditLog::query()->create([
            'user_id' => $user->id,
            'action' => $action,
            'auditable_type' => $user->getMorphClass(),
            'auditable_id' => $user->id,
            'details' => $details,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
