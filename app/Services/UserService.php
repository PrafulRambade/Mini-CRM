<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * User writes. `role` and `is_active` are force-filled here (never mass
 * assigned) and deactivation immediately kills all sessions and API tokens.
 */
class UserService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): User
    {
        $user = new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
        $user->forceFill(['role' => $data['role'], 'is_active' => $data['is_active']])->save();

        return $user;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data, ?string $keepSessionId = null): User
    {
        return DB::transaction(function () use ($user, $data, $keepSessionId) {
            $user->fill(['name' => $data['name'], 'email' => $data['email']]);
            $user->forceFill(['role' => $data['role'], 'is_active' => $data['is_active']]);

            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }

            $user->save();

            if (! $user->is_active || $user->wasChanged('password')) {
                $this->revokeAccess($user, $keepSessionId);
            }

            return $user;
        });
    }

    public function changePassword(User $user, string $password, ?string $keepSessionId = null): void
    {
        $user->password = $password;
        $user->save();

        $this->revokeAccess($user, $keepSessionId);
    }

    public function toggleStatus(User $user): User
    {
        $user->forceFill(['is_active' => ! $user->is_active])->save();

        if (! $user->is_active) {
            $this->revokeAccess($user);
        }

        return $user;
    }

    /**
     * Log the user out everywhere: delete API tokens and database sessions,
     * optionally keeping the session making the request.
     */
    public function revokeAccess(User $user, ?string $keepSessionId = null): void
    {
        $user->tokens()->delete();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->when($keepSessionId, fn ($q) => $q->where('id', '!=', $keepSessionId))
                ->delete();
        }
    }
}
