<?php

namespace App\Policies;

use App\Models\OnlineOrder;
use App\Models\User;

class OnlineOrderPolicy
{
    /**
     * Determine if the user can view the order.
     */
    public function view(User $user, OnlineOrder $onlineOrder): bool
    {
        return $user->id === $onlineOrder->user_id;
    }

    /**
     * Determine if the user can update the order.
     */
    public function update(User $user, OnlineOrder $onlineOrder): bool
    {
        return $user->id === $onlineOrder->user_id;
    }

    /**
     * Determine if the user can delete the order.
     */
    public function delete(User $user, OnlineOrder $onlineOrder): bool
    {
        return $user->id === $onlineOrder->user_id;
    }
}
