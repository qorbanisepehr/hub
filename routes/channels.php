<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Per-recipient private notifications channel (architecture §31). The
// pattern is registered WITHOUT the `private-` prefix — PusherBroadcaster
// strips it from the incoming `channel_name` before matching.
Broadcast::channel('users.{userId}.notifications', function (User $user, string $userId): bool {
    return (string) $user->getKey() === (string) $userId;
});

// Per-recipient private run-progress channel (`App\Support\Realtime`). One
// channel for every kind of long-running work, so a second feature needing
// live progress subscribes to the channel it already has rather than adding
// another rule and another Echo connection. The same self-check as
// notifications: a run's progress is the requester's business and nobody's else.
Broadcast::channel('users.{userId}.runs', function (User $user, string $userId): bool {
    return (string) $user->getKey() === (string) $userId;
});
