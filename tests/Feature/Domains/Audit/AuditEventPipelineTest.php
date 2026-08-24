<?php

use App\Contracts\AuditEvent;
use App\Domains\Audit\Models\AuditLog;
use App\Domains\Auth\Events\LoginSucceeded;
use App\Domains\Document\Events\DocumentUploaded;
use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Employee\Events\EmployeeCreated;
use App\Domains\Employee\Models\Employee;
use App\Models\User;

beforeEach(function () {
    $user = User::factory()->create();
    $this->actingAs($user);
});

describe('Audit Event Pipeline', function () {
    it('records employee.created event dispatched on the bus', function () {
        $employee = Employee::factory()->create();

        event(new EmployeeCreated($employee));

        $log = AuditLog::latest('id')->first();
        expect($log)->not->toBeNull();
        expect($log->event)->toBe('employee.created');
        expect($log->category)->toBe('employee');
        expect($log->actor_type)->toBe('user');
        expect($log->actor_id)->toBe(auth()->id());
        expect($log->subject_type)->toBe('employee');
        expect($log->subject_id)->toBe($employee->id);
    });

    it('records document.uploaded event dispatched on the bus', function () {
        $category = DocumentCategory::create([
            'name' => 'Test Category',
            'slug' => 'test-cat',
            'type' => 'personnel',
        ]);
        $document = Document::factory()->create();
        $owner = Employee::factory()->create();

        event(new DocumentUploaded($document, $owner, $category->name));

        $log = AuditLog::latest('id')->first();
        expect($log)->not->toBeNull();
        expect($log->event)->toBe('document.uploaded');
        expect($log->category)->toBe('document');
    });

    it('records auth.login.success event dispatched on the bus', function () {
        $user = User::factory()->create();

        // Login success is recorded before authentication, so the event must
        // carry its own actor rather than relying on request context.
        event(new LoginSucceeded($user, 'password'));

        $log = AuditLog::latest('id')->first();
        expect($log)->not->toBeNull();
        expect($log->event)->toBe('auth.login.success');
        expect($log->category)->toBe('auth');
        expect($log->actor_id)->toBe($user->id);
    });

    it('captures description from event', function () {
        $employee = Employee::factory()->create();

        event(new EmployeeCreated($employee));

        $log = AuditLog::latest('id')->first();
        expect($log)->not->toBeNull();
        expect($log->description)->toContain('created');
    });

    it('audit records are immutable - update and delete return false', function () {
        $employee = Employee::factory()->create();

        event(new EmployeeCreated($employee));

        $log = AuditLog::latest('id')->first();
        $result = $log->update(['description' => 'hacked']);
        expect($result)->toBeFalse();

        $result = $log->delete();
        expect($result)->toBeFalse();
    });

    it('records any contract event without a blacklist', function () {
        $event = new class implements AuditEvent
        {
            public function eventName(): string
            {
                return 'test.skipped';
            }

            public function category(): string
            {
                return 'test';
            }

            public function actor(): ?array
            {
                return null;
            }

            public function actorRole(): ?array
            {
                return null;
            }

            public function subject(): ?array
            {
                return null;
            }

            public function description(): ?string
            {
                return null;
            }

            public function changes(): ?array
            {
                return null;
            }

            public function metadata(): ?array
            {
                return null;
            }
        };

        event($event);

        $log = AuditLog::latest('id')->first();
        expect($log)->not->toBeNull()
            ->and($log->event)->toBe('test.skipped');
    });
});
