<?php

namespace App\Policies;

use App\Models\AiSetting;
use App\Models\User;

class AiSettingPolicy
{
    /**
     * Determine whether the user can view any AI settings.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can view the AI setting.
     */
    public function view(User $user, AiSetting $aiSetting): bool
    {
        return $user->id === $aiSetting->user_id || $user->isAdmin();
    }

    /**
     * Determine whether the user can create AI settings.
     */
    public function create(User $user): bool
    {
        return $user->isApproved();
    }

    /**
     * Determine whether the user can update the AI setting.
     */
    public function update(User $user, AiSetting $aiSetting): bool
    {
        return $user->id === $aiSetting->user_id || $user->isAdmin();
    }

    /**
     * Determine whether the user can delete the AI setting.
     */
    public function delete(User $user, AiSetting $aiSetting): bool
    {
        return $user->id === $aiSetting->user_id || $user->isAdmin();
    }
}
