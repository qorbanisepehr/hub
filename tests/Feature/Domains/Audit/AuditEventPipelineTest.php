<?php

use App\Contracts\AuditEvent;
use App\Domains\Audit\Models\AuditLog;
use App\Domains\Audit\Services\AuditEventDispatcher;
use App\Domains\Auth\Events\LoginSucceeded;
use App\Domains\Document\Events\DocumentUploaded;
use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Employee\Events\EmployeeCreated;
use App\Domains\Employee\Models\Employee;
use App\Models\User;

beforeEach(function () {
    $this->dispatcher = app(AuditEventDispatcher::class);
});

describe('Audit Event Pipeline', function () {
    it('records employee.created event to audit log', function () {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();
        $event = new EmployeeCreated($employee);

        $this->dispatcher->record($event, $user);

        $log = AuditLog::latest()->first();
        expect($log)->not->toBeNull();
        expect($log->event)->toBe('employee.created');
        expect($log->category)->toBe('employee');
        expect($log->actor_type)->toBe('user');
        expect($log->actor_id)->toBe($user->id);
        expect($log->subject_type)->toBe('employee');
        expect($log->subject_id)->toBe($employee->id);
    });

    it('records document.uploaded event to audit log', function () {
        $user = User::factory()->create();
        $category = DocumentCategory::create([
            'name' => 'Test Category',
            'slug' => 'test-cat',
            'type' => 'personnel',
        ]);
        $document = Document::factory()->create();
        $event = new DocumentUploaded($document, $category, 'personnel');

        $this->dispatcher->record($event, $user);

        $log = AuditLog::latest()->first();
        expect($log)->not->toBeNull();
        expect($log->event)->toBe('document.uploaded');
        expect($log->category)->toBe('document');
    });

    it('records auth.login.success event to audit log', function () {
        $user = User::factory()->create();
        $event = new LoginSucceeded($user, 'password');

        $this->dispatcher->record($event, $user);

        $log = AuditLog::latest()->first();
        expect($log)->not->toBeNull();
        expect($log->event)->toBe('auth.login.success');
        expect($log->category)->toBe('auth');
    });

    it('captures description from event', function () {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();
        $event = new EmployeeCreated($employee);

        $this->dispatcher->record($event, $user);

        $log = AuditLog::latest()->first();
        expect($log)->not->toBeNull();
        expect($log->description)->toContain('created');
    });

    it('audit records are immutable - update and delete return false', function () {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();
        $event = new EmployeeCreated($employee);
        $this->dispatcher->record($event, $user);

        $log = AuditLog::latest()->first();
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

        $result = $this->dispatcher->record($event);

        expect($result)->toBeTrue();

        $log = AuditLog::latest()->first();
        expect($log)->not->toBeNull()
            ->and($log->event)->toBe('test.skipped');
    });
});
