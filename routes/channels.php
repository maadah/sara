<?php

use Illuminate\Support\Facades\Broadcast;

// Default user model channel (used by Laravel Echo / Notifications)
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Custom private channel for real-time sound notifications
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
