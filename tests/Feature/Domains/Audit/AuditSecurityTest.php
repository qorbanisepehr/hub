<?php

use App\Contracts\AuditEvent;
use App\Domains\Audit\Data\AuditContext;
use App\Domains\Audit\Models\AuditLog;
use App\Domains\Audit\Services\AuditEventDispatcher;
use App\Domains\Audit\Services\SensitiveDataSanitizer;
use App\Domains\Employee\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

describe('Audit Security', function () {
    describe('Sensitive Data Sanitization', function () {
        it('redacts password fields', function () {
            $sanitizer = app(SensitiveDataSanitizer::class);

            $data = [
                'password' => 'secret123',
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ];

            $result = $sanitizer->sanitize($data);

            expect($result['password'])->toBe('[REDACTED]');
            expect($result['name'])->toBe('John Doe');
            expect($result['email'])->toBe('john@example.com');
        });

        it('redacts nested sensitive fields', function () {
            $sanitizer = app(SensitiveDataSanitizer::class);

            $data = [
                'user' => [
                    'name' => 'John',
                    'credentials' => [
                        'password' => 'secret',
                        'token' => 'abc123',
                    ],
                ],
            ];

            $result = $sanitizer->sanitize($data);

            expect($result['user']['name'])->toBe('John');
            expect($result['user']['credentials']['password'])->toBe('[REDACTED]');
            expect($result['user']['credentials']['token'])->toBe('[REDACTED]');
        });

        it('redacts all configured sensitive fields', function () {
            $sanitizer = app(SensitiveDataSanitizer::class);
            $sensitiveFields = config('audit.sensitive_fields');

            $data = array_fill_keys($sensitiveFields, 'secret-value');
            $result = $sanitizer->sanitize($data);

            foreach ($sensitiveFields as $field) {
                expect($result[$field])->toBe('[REDACTED]');
            }
        });

        it('preserves non-sensitive fields', function () {
            $sanitizer = app(SensitiveDataSanitizer::class);

            $data = [
                'description' => 'Employee created',
                'category' => 'employee',
                'old_values' => ['name' => 'Old Name'],
                'new_values' => ['name' => 'New Name'],
            ];

            $result = $sanitizer->sanitize($data);

            expect($result)->toBe($data);
        });
    });

    describe('Audit Record Security', function () {
        it('sanitizes sensitive data in old_values', function () {
            $user = User::factory()->create();
            $employee = Employee::factory()->create();

            $event = new class($employee) implements AuditEvent
            {
                public function __construct(
                    private readonly Model $employee,
                ) {}

                public function eventName(): string
                {
                    return 'employee.updated';
                }

                public function category(): string
                {
                    return 'employee';
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
                    return [
                        'type' => 'employee',
                        'id' => $this->employee->getKey(),
                    ];
                }

                public function description(): ?string
                {
                    return 'Employee updated';
                }

                public function changes(): ?array
                {
                    return [
                        'old' => ['password' => 'old-secret', 'name' => 'Old Name'],
                        'new' => ['password' => 'new-secret', 'name' => 'New Name'],
                    ];
                }

                public function metadata(): ?array
                {
                    return null;
                }
            };

            app(AuditEventDispatcher::class)->record($event, $user);

            $log = AuditLog::latest()->first();
            expect($log->old_values['password'])->toBe('[REDACTED]');
            expect($log->old_values['name'])->toBe('Old Name');
            expect($log->new_values['password'])->toBe('[REDACTED]');
            expect($log->new_values['name'])->toBe('New Name');
        });

        it('redacts sensitive query parameters from the stored url', function () {
            $request = Request::create(
                '/api/employees?national_id=1234567890&section=personal_info',
                'GET',
            );

            $url = AuditContext::sanitizedUrl($request);

            expect(str_contains($url, 'national_id=%5BREDACTED%5D'))->toBeTrue()
                ->and(str_contains($url, 'section=personal_info'))->toBeTrue()
                ->and(str_contains($url, '1234567890'))->toBeFalse();
        });

        it('never exposes raw sensitive data in audit logs', function () {
            $user = User::factory()->create();
            $employee = Employee::factory()->create();

            $event = new class($employee) implements AuditEvent
            {
                public function __construct(
                    private readonly Model $employee,
                ) {}

                public function eventName(): string
                {
                    return 'employee.updated';
                }

                public function category(): string
                {
                    return 'employee';
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
                    return [
                        'type' => 'employee',
                        'id' => $this->employee->getKey(),
                    ];
                }

                public function description(): ?string
                {
                    return 'Employee updated';
                }

                public function changes(): ?array
                {
                    return [
                        'old' => ['ssn' => '123-45-6789'],
                        'new' => ['ssn' => '987-65-4321'],
                    ];
                }

                public function metadata(): ?array
                {
                    return null;
                }
            };

            app(AuditEventDispatcher::class)->record($event, $user);

            $log = AuditLog::latest()->first();

            $serialized = serialize($log->toArray());
            expect($serialized)->not->toContain('123-45-6789');
            expect($serialized)->not->toContain('987-65-4321');
        });
    });
});
