<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $target): bool
    {
        return $user->isAdmin();
    }

    /**
     * Admins cannot deactivate themselves or a protected demo account
     * (prevents locking everyone out).
     */
    public function toggleStatus(User $user, User $target): bool
    {
        return $user->isAdmin() && ! $user->is($target) && ! $target->isProtectedDemoAccount();
    }
}
