<?php

use App\Domains\Notification\Notifications\QuestionnaireSubmittedNotification;
use App\Domains\Questionnaire\Models\Questionnaire;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Notifications\Events\BroadcastNotificationCreated;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->user = createUserWithPermissions();
});

/**
 * Point the broadcast manager at the reverb (Pusher) driver for the auth
 * endpoint tests. phpunit sets BROADCAST_CONNECTION=null, whose
 * NullBroadcaster::auth() is a no-op and never runs the channel callback.
 * The Pusher driver signs locally (no network) and routes through
 * verifyUserCanAccessChannel. Channels are then re-registered onto that
 * driver instance.
 */
function useReverbDriver(): void
{
    config(['broadcasting.default' => 'reverb']);
    config(['broadcasting.connections.reverb' => [
        'driver' => 'reverb',
        'key' => 'local-key',
        'secret' => 'local-secret',
        'app_id' => 'local-app',
        'options' => [
            'host' => 'localhost',
            'port' => 8080,
            'scheme' => 'http',
            'useTLS' => false,
        ],
        'client_options' => [],
    ]]);

    require base_path('routes/channels.php');
}

describe('questionnaire.submitted broadcast contract', function () {
    it('names the broadcast event generically as notification.created', function () {
        $notification = new QuestionnaireSubmittedNotification((string) Str::uuid());

        expect($notification->broadcastAs())->toBe('notification.created');
    });

    it('exposes the canonical questionnaire.submitted type for the wire payload', function () {
        $notification = new QuestionnaireSubmittedNotification((string) Str::uuid());

        expect($notification->broadcastType())->toBe('questionnaire.submitted');
    });

    it('leaves channel resolution to the recipient (shared instance isolation)', function () {
        $notification = new QuestionnaireSubmittedNotification((string) Str::uuid());

        expect($notification->broadcastOn())->toBe([]);
    });

    it('carries the flat broadcast payload matching the API contract', function () {
        $notification = new QuestionnaireSubmittedNotification((string) Str::uuid());

        $payload = $notification->toBroadcast($this->user);

        expect($payload)->toHaveKeys(['title', 'body', 'data', 'read_at', 'created_at']);
        expect($payload['title'])->toBe(__('notifications.questionnaire_submitted.title'));
        expect($payload['body'])->toBe(__('notifications.questionnaire_submitted.body'));
        expect($payload['read_at'])->toBeNull();
        expect($payload['created_at'])->toBeString();
        expect($payload['data'])->toBe($notification->toDatabase($this->user));
    });

    it('resolves the per-recipient private channel for a user', function () {
        expect($this->user->receivesBroadcastNotificationsOn(
            new QuestionnaireSubmittedNotification((string) Str::uuid()),
        ))->toBe('users.'.$this->user->id.'.notifications');
    });
});

describe('private channel registration', function () {
    it('registers the users.{id}.notifications channel pattern', function () {
        expect(Broadcast::getChannels())->toHaveKey('users.{userId}.notifications');
    });
});

describe('broadcast dispatch', function () {
    it('queues a broadcast event for the recipient with the wire contract', function () {
        Queue::fake();

        $viewer = createUserWithPermissions(['questionnaire.view']);
        $questionnaire = Questionnaire::create(['status' => 'submitted']);

        $viewer->notify(new QuestionnaireSubmittedNotification((string) $questionnaire->id));

        Queue::assertPushed(BroadcastEvent::class, function (BroadcastEvent $job) use ($viewer, $questionnaire): bool {
            $event = $job->event;
            expect($event)->toBeInstanceOf(BroadcastNotificationCreated::class);

            $channels = $event->broadcastOn();
            expect($channels)->toHaveCount(1);
            expect($channels[0])->toBeInstanceOf(PrivateChannel::class);
            expect($channels[0]->name)->toBe('private-users.'.$viewer->id.'.notifications');

            expect($event->broadcastAs())->toBe('notification.created');

            $payload = $event->broadcastWith();
            expect($payload['type'])->toBe('questionnaire.submitted');
            expect($payload['title'])->toBe(__('notifications.questionnaire_submitted.title'));
            expect($payload['read_at'])->toBeNull();
            expect($payload['data']['questionnaire_id'])->toBe((string) $questionnaire->id);

            return true;
        });
    });
});

describe('POST /api/broadcasting/auth', function () {
    it('authorizes the recipient on their own private channel', function () {
        useReverbDriver();

        actingAs($this->user)
            ->postJson('/api/broadcasting/auth', [
                'channel_name' => 'private-users.'.$this->user->id.'.notifications',
                'socket_id' => '1234.5678',
            ])
            ->assertOk()
            ->assertJsonStructure(['auth']);
    });

    it('rejects a different user accessing someone else\'s private channel', function () {
        useReverbDriver();

        $other = createUserWithPermissions();

        actingAs($other)
            ->postJson('/api/broadcasting/auth', [
                'channel_name' => 'private-users.'.$this->user->id.'.notifications',
                'socket_id' => '1234.5678',
            ])
            ->assertForbidden();
    });

    it('requires authentication', function () {
        useReverbDriver();

        $this->postJson('/api/broadcasting/auth', [
            'channel_name' => 'private-users.1.notifications',
            'socket_id' => '1234.5678',
        ])->assertStatus(401);
    });
});
