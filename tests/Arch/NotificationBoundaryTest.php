<?php

use App\Domains\Notification\Contracts\InAppNotification;
use App\Domains\Notification\Support\NotificationType;
use BackedEnum;
use Tests\TestCase;

uses(TestCase::class);

/*
 * Notification ownership split (architecture §11, §13, §99).
 *
 * A business domain owns the CONTENT of its notifications - the vocabulary and
 * the payload - and extends the published InAppNotification contract inside its
 * own `Notifications` namespace. The Notification domain owns the MECHANISM and
 * the contract: channels, broadcast, read state, the API, canonical type
 * naming and Authorization-aware recipient resolution.
 *
 * Three things are therefore violations:
 *   1. the Notification domain owning content (a Notifications/ folder there);
 *   2. one domain's notifications depending on another domain's notifications;
 *   3. the mechanism depending on content at all - it subscribes to business
 *      events, never to a domain's notification classes.
 */

/**
 * Notification classes discovered on disk, keyed by FQCN.
 *
 * @return array<class-string, string>
 */
function discoveredNotificationClasses(): array
{
    $classes = [];

    foreach (glob(app_path('Domains/*/Notifications/*.php')) as $file) {
        $class = 'App\\'.str_replace(['/', '.php'], ['\\', ''], str_replace(app_path().'\\', '', $file));

        if (class_exists($class)) {
            $classes[$class] = $file;
        }
    }

    return $classes;
}

/**
 * Build an instance with type-appropriate sample constructor arguments, so the
 * contract can be inspected without knowing each type's own vocabulary.
 *
 * An enum parameter resolves to its first case rather than to the `sample`
 * string, which is what a notification carrying a reason or an outcome needs:
 * the alternatives are the wrong type and throw, so the boundary checks could
 * not be run against that notification at all. First case, not a "best" case —
 * these tests assert the contract holds for *any* instance, and a value that
 * only works for one case would not prove that.
 */
function sampleNotification(string $class): InAppNotification
{
    $constructor = (new ReflectionClass($class))->getConstructor();

    $arguments = array_map(
        function (ReflectionParameter $parameter): mixed {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && enum_exists($type->getName())) {
                /** @var class-string<BackedEnum> $enum */
                $enum = $type->getName();

                return $enum::cases()[0];
            }

            return match ((string) $type) {
                'int' => 1,
                'bool' => true,
                'array' => [],
                default => 'sample',
            };
        },
        $constructor?->getParameters() ?? [],
    );

    return new $class(...$arguments);
}

test('the Notification domain owns no notification content of its own', function () {
    expect(glob(app_path('Domains/Notification/Notifications')))->toBe([]);
});

// Declared at file scope like the other arch tests, so Pest counts the
// expectations they perform instead of reporting the test as risky.
arch("Auth notifications never depend on another domain's notifications")
    ->expect('App\Domains\Auth\Notifications')
    ->not->toUse(['App\Domains\Questionnaire\Notifications']);

arch("Questionnaire notifications never depend on another domain's notifications")
    ->expect('App\Domains\Questionnaire\Notifications')
    ->not->toUse(['App\Domains\Auth\Notifications']);

// The mechanism must stay ignorant of content: contracts, channels, services
// and support know nothing about any domain's notification classes. The one
// sanctioned bridge is Listeners - it turns a business event into a dispatch
// and owns the channel-selection policy (rule 11), so that is where the two
// halves meet.
foreach (['Contracts', 'Channels', 'Services', 'Support'] as $mechanism) {
    arch("the Notification {$mechanism} never reaches into a domain notification class")
        ->expect('App\Domains\Notification\\'.$mechanism)
        ->not->toUse([
            'App\Domains\Auth\Notifications',
            'App\Domains\Questionnaire\Notifications',
        ]);
}

test('every notification class satisfies the published in-app contract', function () {
    $classes = discoveredNotificationClasses();

    expect($classes)->not->toBeEmpty();

    foreach (array_keys($classes) as $class) {
        expect(is_subclass_of($class, InAppNotification::class))->toBeTrue();
    }
});

test('the registered types and the notification classes on disk are the same set', function () {
    /** @var array<int, class-string> $registered */
    $registered = config('notifications.types', []);

    $discovered = array_keys(discoveredNotificationClasses());

    sort($registered);
    sort($discovered);

    expect($registered)->toBe($discovered);
});

test('every registered type exposes a canonical type and both translations', function () {
    /** @var array<int, class-string> $registered */
    $registered = config('notifications.types', []);

    expect($registered)->not->toBeEmpty();

    $original = app()->getLocale();

    foreach ($registered as $class) {
        $type = NotificationType::canonical($class);

        // Canonical dot notation: {domain}.{occurrence} (§23).
        expect($type)->toMatch('/^[a-z_]+\.[a-z_]+$/');

        // Translation-first content in both locales (§26).
        foreach (['fa', 'en'] as $locale) {
            app()->setLocale($locale);

            expect(NotificationType::translated($type, 'title'))->toBeString()->not->toBeEmpty();
            expect(NotificationType::translated($type, 'body'))->toBeString()->not->toBeEmpty();
        }
    }

    app()->setLocale($original);
});

test('every registered type keeps the in-app channel policy and the generic event name', function () {
    /** @var array<int, class-string> $registered */
    $registered = config('notifications.types', []);

    foreach ($registered as $class) {
        $notification = sampleNotification($class);

        // database is the source of truth and broadcast rides along; an external
        // channel is a separate queued Mailable, never a channel here (N6).
        expect($notification->via(new stdClass))->toBe(['database', 'broadcast'])
            ->and($notification->broadcastAs())->toBe('notification.created')
            ->and($notification->broadcastOn())->toBe([])
            ->and($notification->broadcastType())->toBe(NotificationType::canonical($class));
    }
});

test('every registered type declares a destination reference, possibly an explicit null', function () {
    /** @var array<int, class-string> $registered */
    $registered = config('notifications.types', []);

    foreach ($registered as $class) {
        $data = sampleNotification($class)->toDatabase(new stdClass);

        // The client derives deep links from `resource`, so the key is always
        // present; when set it is a {type, id} reference, never a URL (§25).
        expect($data)->toHaveKey('resource');

        if ($data['resource'] !== null) {
            expect($data['resource'])->toBeArray()
                ->and($data['resource'])->toHaveKeys(['type', 'id'])
                ->and($data['resource']['type'])->toBeString()
                ->and($data['resource']['id'])->toBeString();
        }
    }
});

test('every registered type broadcasts a payload matching the resource contract', function () {
    /** @var array<int, class-string> $registered */
    $registered = config('notifications.types', []);

    foreach ($registered as $class) {
        $notification = sampleNotification($class);
        $payload = $notification->toBroadcast(new stdClass);

        expect($payload)->toHaveKeys(['title', 'body', 'data', 'read_at', 'created_at'])
            ->and($payload['read_at'])->toBeNull()
            ->and($payload['data'])->toBe($notification->toDatabase(new stdClass))
            ->and($payload['title'])->toBeString();
    }
});
