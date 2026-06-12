<?php

namespace App\Policies;

use App\Models\SocialAccount;
use App\Models\User;

class SocialAccountPolicy
{
    /**
     * Determine whether the user can view any social accounts.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the social account.
     */
    public function view(User $user, SocialAccount $socialAccount): bool
    {
        return $user->id === $socialAccount->user_id || $user->isAdmin();
    }

    /**
     * Determine whether the user can create social accounts.
     */
    public function create(User $user): bool
    {
        return $user->isApproved();
    }

    /**
     * Determine whether the user can update the social account.
     */
    public function update(User $user, SocialAccount $socialAccount): bool
    {
        return $user->id === $socialAccount->user_id || $user->isAdmin();
    }

    /**
     * Determine whether the user can delete the social account.
     */
    public function delete(User $user, SocialAccount $socialAccount): bool
    {
        return $user->id === $socialAccount->user_id || $user->isAdmin();
    }

    /**
     * Determine whether the user can disconnect the social account.
     */
    public function disconnect(User $user, SocialAccount $socialAccount): bool
    {
        return $user->id === $socialAccount->user_id;
    }
}
