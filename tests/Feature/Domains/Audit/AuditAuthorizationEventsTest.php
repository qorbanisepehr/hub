<?php

use App\Domains\Authorization\Events\PermissionAssigned;
use App\Domains\Authorization\Events\RoleCreated;
use App\Domains\Authorization\Events\RoleDeleted;
use App\Domains\Authorization\Events\RoleToggled;
use App\Domains\Authorization\Events\RoleUpdated;
use App\Domains\Authorization\Events\UserCreated;
use App\Domains\Authorization\Events\UserUpdated;
use App\Domains\Authorization\Models\Role;
use App\Models\User;

test('RoleCreated event has correct properties', function () {
    $role = Role::create(['name' => 'test-role', 'display_name' => 'Test Role', 'is_active' => true]);

    $event = new RoleCreated($role, [1, 2, 3]);

    expect($event->eventName())->toBe('authorization.role.created')
        ->and($event->category())->toBe('authorization')
        ->and($event->subject())->toBe([
            'type' => 'role',
            'id' => $role->id,
            'name' => 'test-role',
            'display_name' => 'Test Role',
        ])
        ->and($event->changes())->toBe(['permissions' => [1, 2, 3]]);
});

test('RoleUpdated event has correct properties', function () {
    $role = Role::create(['name' => 'old-name', 'display_name' => 'Old Name', 'is_active' => true]);

    $event = new RoleUpdated($role, ['name' => 'old-name', 'display_name' => 'Old Name'], ['name' => 'new-name', 'display_name' => 'New Name']);

    expect($event->eventName())->toBe('authorization.role.updated')
        ->and($event->category())->toBe('authorization')
        ->and($event->changes())->toBe([
            'name' => ['old' => 'old-name', 'new' => 'new-name'],
            'display_name' => ['old' => 'Old Name', 'new' => 'New Name'],
        ]);
});

test('RoleUpdated event returns null changes when no differences', function () {
    $role = Role::create(['name' => 'same', 'display_name' => 'Same', 'is_active' => true]);

    $event = new RoleUpdated($role, ['name' => 'same'], ['name' => 'same']);

    expect($event->changes())->toBeNull();
});

test('RoleDeleted event has correct properties', function () {
    $role = Role::create(['name' => 'test-role', 'display_name' => 'Test Role', 'is_active' => true]);

    $event = new RoleDeleted($role);

    expect($event->eventName())->toBe('authorization.role.deleted')
        ->and($event->category())->toBe('authorization')
        ->and($event->description())->toBe('Role Test Role deleted');
});

test('RoleToggled event has correct properties', function () {
    $role = Role::create(['name' => 'test-role', 'display_name' => 'Test Role', 'is_active' => true]);

    $event = new RoleToggled($role, false);

    expect($event->eventName())->toBe('authorization.role.toggled')
        ->and($event->category())->toBe('authorization')
        ->and($event->description())->toBe('Role Test Role deactivated')
        ->and($event->changes())->toBe([
            'is_active' => ['old' => true, 'new' => false],
        ]);
});

test('UserCreated event has correct properties', function () {
    $user = User::factory()->create(['name' => 'Test User', 'email' => 'test@example.com']);

    $event = new UserCreated($user);

    expect($event->eventName())->toBe('authorization.user.created')
        ->and($event->category())->toBe('authorization')
        ->and($event->subject())->toBe([
            'type' => 'user',
            'id' => $user->id,
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
});

test('UserUpdated event has correct properties', function () {
    $user = User::factory()->create(['name' => 'Old Name', 'email' => 'old@example.com']);

    $event = new UserUpdated($user, ['name' => 'Old Name', 'email' => 'old@example.com'], ['name' => 'New Name', 'email' => 'new@example.com']);

    expect($event->eventName())->toBe('authorization.user.updated')
        ->and($event->category())->toBe('authorization')
        ->and($event->changes())->toBe([
            'name' => ['old' => 'Old Name', 'new' => 'New Name'],
            'email' => ['old' => 'old@example.com', 'new' => 'new@example.com'],
        ]);
});

test('PermissionAssigned event has correct properties', function () {
    $role = Role::create(['name' => 'test-role', 'display_name' => 'Test Role', 'is_active' => true]);

    $event = new PermissionAssigned($role, [10, 20, 30]);

    expect($event->eventName())->toBe('authorization.permission.assigned')
        ->and($event->category())->toBe('authorization')
        ->and($event->changes())->toBe(['permission_ids' => [10, 20, 30]]);
});
