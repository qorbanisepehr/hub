<?php

use App\Domains\Notification\Resources\NotificationResource;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->user = createUserWithPermissions();
});

/**
 * Seed notifications for the given user (recipient-scoped records).
 *
 * @return list<DatabaseNotification>
 */
function createNotifications($user, array $specs): array
{
    $records = [];

    foreach ($specs as $spec) {
        $records[] = $user->notifications()->create([
            'id' => $spec['id'] ?? (string) Str::uuid(),
            'type' => $spec['type'] ?? 'App\Domains\Notification\Notifications\QuestionnaireSubmittedNotification',
            'data' => $spec['data'] ?? ['questionnaire_id' => (string) Str::uuid()],
            'read_at' => $spec['read_at'] ?? null,
            'created_at' => $spec['created_at'] ?? now(),
        ]);
    }

    return $records;
}

describe('notification API', function () {
    describe('GET /api/notifications', function () {
        it('requires authentication', function () {
            $this->getJson('/api/notifications')->assertStatus(401);
        });

        it('returns the authenticated user\'s notifications, newest first', function () {
            [$older, $recent, $newest] = createNotifications($this->user, [
                ['created_at' => now()->subDays(2)],
                ['created_at' => now()->subDay()],
                ['created_at' => now()],
            ]);

            actingAs($this->user)
                ->getJson('/api/notifications')
                ->assertOk()
                ->assertJsonCount(3, 'data')
                ->assertJsonPath('data.0.id', $newest->id)
                ->assertJsonPath('data.1.id', $recent->id)
                ->assertJsonPath('data.2.id', $older->id)
                ->assertJsonStructure([
                    'data' => ['*' => ['id', 'type', 'title', 'body', 'data', 'read_at', 'created_at']],
                    'links',
                    'meta',
                ]);
        });

        it('never exposes another user\'s notifications', function () {
            createNotifications($this->user, [[]]);
            $other = createUserWithPermissions();
            createNotifications($other, [
                ['type' => 'App\Domains\Notification\Notifications\DocumentReceivedNotification'],
            ]);

            actingAs($this->user)
                ->getJson('/api/notifications')
                ->assertOk()
                ->assertJsonCount(1, 'data');
        });

        it('filters to unread when unread=1', function () {
            createNotifications($this->user, [
                ['read_at' => null],
                ['read_at' => now()->subHour()],
                ['read_at' => null],
            ]);

            actingAs($this->user)
                ->getJson('/api/notifications?unread=1')
                ->assertOk()
                ->assertJsonCount(2, 'data');
        });

        it('filters to read when unread=0', function () {
            createNotifications($this->user, [
                ['read_at' => null],
                ['read_at' => now()->subHour()],
            ]);

            actingAs($this->user)
                ->getJson('/api/notifications?unread=0')
                ->assertOk()
                ->assertJsonCount(1, 'data');
        });

        it('exposes the canonical dot type and data payload', function () {
            createNotifications($this->user, [
                ['type' => 'App\Domains\Notification\Notifications\QuestionnaireSubmittedNotification'],
            ]);

            actingAs($this->user)
                ->getJson('/api/notifications')
                ->assertOk()
                ->assertJsonPath('data.0.type', 'questionnaire.submitted');
        });

        it('respects per_page pagination', function () {
            createNotifications($this->user, array_fill(0, 5, []));

            actingAs($this->user)
                ->getJson('/api/notifications?per_page=2')
                ->assertOk()
                ->assertJsonCount(2, 'data')
                ->assertJsonPath('meta.per_page', 2);
        });
    });

    describe('GET /api/notifications/unread-count', function () {
        it('requires authentication', function () {
            $this->getJson('/api/notifications/unread-count')->assertStatus(401);
        });

        it('counts only the authenticated user\'s unread notifications', function () {
            createNotifications($this->user, [
                ['read_at' => null],
                ['read_at' => now()->subHour()],
                ['read_at' => null],
            ]);
            $other = createUserWithPermissions();
            createNotifications($other, [['read_at' => null]]);

            actingAs($this->user)
                ->getJson('/api/notifications/unread-count')
                ->assertOk()
                ->assertJsonPath('data.count', 2);
        });
    });

    describe('POST /api/notifications/{notification}/read', function () {
        it('requires authentication', function () {
            $this->postJson('/api/notifications/some-id/read')->assertStatus(401);
        });

        it('marks an own notification as read', function () {
            [$notification] = createNotifications($this->user, [['read_at' => null]]);

            actingAs($this->user)
                ->postJson("/api/notifications/{$notification->id}/read")
                ->assertOk()
                ->assertJsonPath('data.id', $notification->id)
                ->assertJsonStructure(['data' => ['read_at']]);

            $this->assertNotNull($notification->fresh()->read_at);
        });

        it('is idempotent when already read', function () {
            $readAt = now()->subHour();
            [$notification] = createNotifications($this->user, [['read_at' => $readAt]]);

            actingAs($this->user)
                ->postJson("/api/notifications/{$notification->id}/read")
                ->assertOk()
                ->assertJsonPath('data.id', $notification->id);

            $this->assertTrue($notification->fresh()->read_at->equalTo($readAt->copy()->startOfSecond()));
        });

        it('returns 404 for another user\'s notification', function () {
            $other = createUserWithPermissions();
            [$foreign] = createNotifications($other, [['read_at' => null]]);

            actingAs($this->user)
                ->postJson("/api/notifications/{$foreign->id}/read")
                ->assertStatus(404);
        });

        it('returns 404 for a nonexistent notification', function () {
            actingAs($this->user)
                ->postJson('/api/notifications/'.Str::uuid().'/read')
                ->assertStatus(404);
        });
    });

    describe('POST /api/notifications/read-all', function () {
        it('requires authentication', function () {
            $this->postJson('/api/notifications/read-all')->assertStatus(401);
        });

        it('marks only the authenticated user\'s unread notifications as read', function () {
            createNotifications($this->user, [
                ['read_at' => null],
                ['read_at' => null],
                ['read_at' => now()->subHour()],
            ]);
            $other = createUserWithPermissions();
            [$foreign] = createNotifications($other, [['read_at' => null]]);

            actingAs($this->user)
                ->postJson('/api/notifications/read-all')
                ->assertOk()
                ->assertJsonPath('data.updated', 2);

            $this->assertSame(0, $this->user->unreadNotifications()->count());
            $this->assertNull($foreign->fresh()->read_at);
        });
    });

    describe('POST /api/notifications/delete-read', function () {
        it('requires authentication', function () {
            $this->postJson('/api/notifications/delete-read')->assertStatus(401);
        });

        it('deletes only the authenticated user\'s read notifications and returns the count', function () {
            createNotifications($this->user, [
                ['read_at' => now()->subHour()],
                ['read_at' => now()->subDay()],
                ['read_at' => null],
                ['read_at' => null],
            ]);

            $other = createUserWithPermissions();
            [$foreign] = createNotifications($other, [['read_at' => now()->subHour()]]);

            actingAs($this->user)
                ->postJson('/api/notifications/delete-read')
                ->assertOk()
                ->assertJsonPath('data.deleted', 2);

            // Unread notifications stay put; the other user's read one is untouched.
            $this->assertSame(2, $this->user->notifications()->count());
            $this->assertSame(0, $this->user->notifications()->whereNotNull('read_at')->count());
            $this->assertNotNull($foreign->fresh()->read_at);
        });

        it('deletes nothing when there are no read notifications', function () {
            createNotifications($this->user, [
                ['read_at' => null],
            ]);

            actingAs($this->user)
                ->postJson('/api/notifications/delete-read')
                ->assertOk()
                ->assertJsonPath('data.deleted', 0);

            $this->assertSame(1, $this->user->notifications()->count());
        });
    });
});

describe('notification resource', function () {
    it('derives canonical types from notification class names', function () {
        $user = createUserWithPermissions();
        $notification = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Domains\Notification\Notifications\EmployeeApprovalRequestedNotification',
            'data' => ['employee_id' => 1],
        ]);

        $resource = new NotificationResource($notification->withoutRelations());

        expect($resource->resolve()['type'])->toBe('employee.approval_requested');
    });
});
