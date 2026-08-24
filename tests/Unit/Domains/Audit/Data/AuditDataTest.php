<?php

namespace Tests\Unit\Domains\Audit\Data;

use App\Contracts\AuditEvent;
use App\Domains\Audit\Data\AuditContext;
use App\Domains\Audit\Data\AuditData;
use Tests\TestCase;

class AuditDataTest extends TestCase
{
    public function test_from_event_builds_data_from_event_and_context(): void
    {
        $event = $this->createAuditEvent([
            'eventName' => 'employee.updated',
            'category' => 'employee',
            'actor' => ['type' => 'user', 'id' => 42],
            'actorRole' => ['id' => 1, 'name' => 'admin'],
            'subject' => ['type' => 'employee', 'id' => 10],
            'changes' => ['old' => ['name' => 'Old'], 'new' => ['name' => 'New']],
            'description' => 'Employee updated',
            'metadata' => ['section' => 'personal'],
        ]);

        $context = new AuditContext(
            ipAddress: '127.0.0.1',
            userAgent: 'TestAgent',
            url: 'http://example.com/api/employees/10',
            method: 'PUT',
            requestId: 'req-123',
            traceId: 'trace-456',
            actorId: 42,
            actorType: 'user',
            actorRoleId: 1,
            actorRoleName: 'admin',
        );

        $data = AuditData::fromEvent($event, $context);

        $this->assertNotEmpty($data->eventId);
        $this->assertSame('employee.updated', $data->event);
        $this->assertSame('employee', $data->category);
        $this->assertSame('user', $data->actorType);
        $this->assertSame(42, $data->actorId);
        $this->assertSame(1, $data->actorRoleId);
        $this->assertSame('admin', $data->actorRoleName);
        $this->assertSame('employee', $data->subjectType);
        $this->assertSame(10, $data->subjectId);
        $this->assertSame(['name' => 'Old'], $data->oldValues);
        $this->assertSame(['name' => 'New'], $data->newValues);
        $this->assertSame('Employee updated', $data->description);
        $this->assertSame(['section' => 'personal'], $data->metadata);
        $this->assertSame('127.0.0.1', $data->ipAddress);
        $this->assertSame('req-123', $data->requestId);
    }

    public function test_from_event_uses_context_actor_when_event_has_no_actor(): void
    {
        $event = $this->createAuditEvent([
            'eventName' => 'document.uploaded',
            'category' => 'document',
            'actor' => null,
            'actorRole' => null,
            'subject' => ['type' => 'document', 'id' => 5],
            'changes' => null,
            'description' => null,
            'metadata' => null,
        ]);

        $context = new AuditContext(
            ipAddress: null,
            userAgent: null,
            url: null,
            method: null,
            requestId: null,
            traceId: null,
            actorId: 99,
            actorType: 'user',
            actorRoleId: null,
            actorRoleName: null,
        );

        $data = AuditData::fromEvent($event, $context);

        $this->assertSame(99, $data->actorId);
        $this->assertSame('user', $data->actorType);
    }

    public function test_to_array_maps_all_fields(): void
    {
        $event = $this->createAuditEvent([
            'eventName' => 'auth.login.success',
            'category' => 'auth',
            'actor' => ['type' => 'user', 'id' => 1],
            'actorRole' => null,
            'subject' => null,
            'changes' => null,
            'description' => 'Login',
            'metadata' => ['method' => 'password'],
        ]);

        $context = new AuditContext(
            ipAddress: '10.0.0.1',
            userAgent: 'Browser',
            url: 'http://example.com/login',
            method: 'POST',
            requestId: null,
            traceId: null,
            actorId: 1,
            actorType: 'user',
            actorRoleId: null,
            actorRoleName: null,
        );

        $data = AuditData::fromEvent($event, $context);
        $array = $data->toArray();

        $this->assertArrayHasKey('event_id', $array);
        $this->assertSame('auth.login.success', $array['event']);
        $this->assertSame('auth', $array['category']);
        $this->assertSame('user', $array['actor_type']);
        $this->assertSame(1, $array['actor_id']);
        $this->assertNull($array['actor_role_id']);
        $this->assertNull($array['subject_type']);
        $this->assertNull($array['subject_id']);
        $this->assertSame('Login', $array['description']);
        $this->assertSame(['method' => 'password'], $array['metadata']);
        $this->assertSame('10.0.0.1', $array['ip_address']);
        $this->assertSame('POST', $array['method']);
        $this->assertArrayHasKey('created_at', $array);
    }

    private function createAuditEvent(array $overrides = []): AuditEvent
    {
        $defaults = [
            'eventName' => 'test.event',
            'category' => 'test',
            'actor' => null,
            'actorRole' => null,
            'subject' => null,
            'changes' => null,
            'description' => null,
            'metadata' => null,
        ];

        $config = array_merge($defaults, $overrides);

        return new class($config) implements AuditEvent
        {
            public function __construct(
                private readonly array $config,
            ) {}

            public function eventName(): string
            {
                return $this->config['eventName'];
            }

            public function category(): string
            {
                return $this->config['category'];
            }

            public function actor(): ?array
            {
                return $this->config['actor'];
            }

            public function actorRole(): ?array
            {
                return $this->config['actorRole'];
            }

            public function subject(): ?array
            {
                return $this->config['subject'];
            }

            public function description(): ?string
            {
                return $this->config['description'];
            }

            public function changes(): ?array
            {
                return $this->config['changes'];
            }

            public function metadata(): ?array
            {
                return $this->config['metadata'];
            }
        };
    }
}
