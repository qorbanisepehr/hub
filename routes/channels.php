<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Per-recipient private notifications channel (architecture §31). The
// pattern is registered WITHOUT the `private-` prefix — PusherBroadcaster
// strips it from the incoming `channel_name` before matching.
Broadcast::channel('users.{userId}.notifications', function (User $user, string $userId): bool {
    return (string) $user->getKey() === (string) $userId;
});
