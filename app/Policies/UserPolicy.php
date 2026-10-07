<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /** Approve or reject a registration: a therapist, acting on a parent account that isn't her own. */
    public function review(User $user, User $account): bool
    {
        return $user->isTherapist()
            && $user->isApproved()
            && ! $account->isTherapist()
            && (int) $user->id !== (int) $account->id;
    }
}
