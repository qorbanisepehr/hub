<?php

use App\Domains\Authorization\Models\Permission;
use App\Domains\Authorization\Models\PermissionGroup;
use App\Domains\Authorization\Models\Role;
use App\Domains\Notification\Listeners\NotifyQuestionnaireViewers;
use App\Domains\Notification\Notifications\QuestionnaireSubmittedNotification;
use App\Domains\Questionnaire\Events\QuestionnaireSubmitted;
use App\Domains\Questionnaire\Models\Questionnaire;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

/**
 * Build a submitted questionnaire for the event pipeline.
 */
function makeSubmittedQuestionnaire(): Questionnaire
{
    return Questionnaire::create(['status' => 'submitted']);
}

describe('questionnaire.submitted notification pipeline', function () {
    it('registers the listener against the questionnaire.submitted event', function () {
        Event::fake();

        Event::assertListening(QuestionnaireSubmitted::class, NotifyQuestionnaireViewers::class);
    });

    it('defers the listener until after the database transaction commits', function () {
        expect(app(NotifyQuestionnaireViewers::class))
            ->toBeInstanceOf(ShouldHandleEventsAfterCommit::class);
    });

    it('sends a database notification to questionnaire.view holders', function () {
        Notification::fake();

        $viewer = createUserWithPermissions(['questionnaire.view']);
        $outsider = createUserWithPermissions();

        $questionnaire = makeSubmittedQuestionnaire();

        app(NotifyQuestionnaireViewers::class)->handle(new QuestionnaireSubmitted($questionnaire));

        Notification::assertSentTo($viewer, QuestionnaireSubmittedNotification::class);
        Notification::assertNotSentTo($outsider, QuestionnaireSubmittedNotification::class);
    });

    it('carries the questionnaire id and stable resource reference as data', function () {
        Notification::fake();

        $viewer = createUserWithPermissions(['questionnaire.view']);
        $questionnaire = makeSubmittedQuestionnaire();

        app(NotifyQuestionnaireViewers::class)->handle(new QuestionnaireSubmitted($questionnaire));

        Notification::assertSentTo(
            $viewer,
            QuestionnaireSubmittedNotification::class,
            function (QuestionnaireSubmittedNotification $notification, array $channels) use ($questionnaire, $viewer): bool {
                expect($channels)->toBe(['database']);
                expect($notification->toDatabase($viewer))->toBe([
                    'questionnaire_id' => (string) $questionnaire->id,
                    'resource' => [
                        'type' => 'questionnaire',
                        'id' => (string) $questionnaire->id,
                        'action' => 'review',
                    ],
                ]);

                return true;
            },
        );
    });

    it('does not notify a user whose role is explicitly denied the permission', function () {
        Notification::fake();

        $viewer = createUserWithPermissions(['questionnaire.view']);
        $permission = Permission::where('name', 'questionnaire.view')->firstOrFail();
        $viewer->activeRole->denyPermission($permission->id);

        $questionnaire = makeSubmittedQuestionnaire();

        app(NotifyQuestionnaireViewers::class)->handle(new QuestionnaireSubmitted($questionnaire));

        Notification::assertNotSentTo($viewer, QuestionnaireSubmittedNotification::class);
    });

    it('honors allow rules inherited from the parent role chain', function () {
        Notification::fake();

        $group = PermissionGroup::firstOrCreate(
            ['slug' => 'test'],
            ['name' => 'Test Group', 'sort_order' => 999],
        );
        $permission = Permission::firstOrCreate(
            ['name' => 'questionnaire.view'],
            ['display_name' => 'questionnaire.view', 'group_id' => $group->id],
        );

        $parent = Role::create([
            'name' => 'parent-'.uniqid(),
            'display_name' => 'Parent',
            'is_active' => true,
        ]);
        $parent->permissions()->attach($permission);

        $child = Role::create([
            'name' => 'child-'.uniqid(),
            'display_name' => 'Child',
            'is_active' => true,
            'parent_id' => $parent->id,
        ]);

        $viewer = User::factory()->create();
        $viewer->assignRole($child->id, true);

        $questionnaire = makeSubmittedQuestionnaire();

        app(NotifyQuestionnaireViewers::class)->handle(new QuestionnaireSubmitted($questionnaire));

        Notification::assertSentTo($viewer, QuestionnaireSubmittedNotification::class);
    });

    it('skips inactive users even when their role grants the permission', function () {
        Notification::fake();

        $viewer = createUserWithPermissions(['questionnaire.view']);
        $viewer->update(['is_active' => false]);

        $questionnaire = makeSubmittedQuestionnaire();

        app(NotifyQuestionnaireViewers::class)->handle(new QuestionnaireSubmitted($questionnaire));

        Notification::assertNotSentTo($viewer, QuestionnaireSubmittedNotification::class);
    });

    it('persists the notification through the database channel as the source of truth', function () {
        $viewer = createUserWithPermissions(['questionnaire.view']);
        $questionnaire = makeSubmittedQuestionnaire();

        app(NotifyQuestionnaireViewers::class)->handle(new QuestionnaireSubmitted($questionnaire));

        $row = $viewer->notifications()->first();

        expect($row)->not->toBeNull();
        expect($row->type)->toBe(QuestionnaireSubmittedNotification::class);
        expect($row->data['questionnaire_id'])->toBe((string) $questionnaire->id);
    });
});
