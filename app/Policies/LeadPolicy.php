<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Lead $lead): bool
    {
        return $this->owns($user, $lead);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Lead $lead): bool
    {
        return $this->owns($user, $lead);
    }

    public function convert(User $user, Lead $lead): bool
    {
        return $this->owns($user, $lead) && ! $lead->isConverted();
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $user->isAdmin();
    }

    /**
     * Only admins may (re)assign leads to other users.
     */
    public function assign(User $user): bool
    {
        return $user->isAdmin();
    }

    private function owns(User $user, Lead $lead): bool
    {
        return $user->isAdmin() || $lead->assigned_to === $user->id;
    }
}
