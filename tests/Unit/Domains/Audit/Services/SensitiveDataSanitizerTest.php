<?php

namespace Tests\Unit\Domains\Audit\Services;

use App\Domains\Audit\Services\SensitiveDataSanitizer;
use Tests\TestCase;

class SensitiveDataSanitizerTest extends TestCase
{
    private SensitiveDataSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new SensitiveDataSanitizer;
    }

    public function test_redacts_sensitive_fields(): void
    {
        $data = [
            'name' => 'John',
            'password' => 'secret123',
            'email' => 'john@example.com',
        ];

        $result = $this->sanitizer->sanitize($data);

        $this->assertSame('John', $result['name']);
        $this->assertSame('[REDACTED]', $result['password']);
        $this->assertSame('john@example.com', $result['email']);
    }

    public function test_redacts_nested_sensitive_fields(): void
    {
        $data = [
            'user' => [
                'name' => 'John',
                'credentials' => [
                    'password' => 'secret123',
                    'token' => 'abc123',
                ],
            ],
        ];

        $result = $this->sanitizer->sanitize($data);

        $this->assertSame('John', $result['user']['name']);
        $this->assertSame('[REDACTED]', $result['user']['credentials']['password']);
        $this->assertSame('[REDACTED]', $result['user']['credentials']['token']);
    }

    public function test_preserves_non_sensitive_fields(): void
    {
        $data = [
            'event' => 'employee.updated',
            'old_values' => ['name' => 'Old Name'],
            'new_values' => ['name' => 'New Name'],
        ];

        $result = $this->sanitizer->sanitize($data);

        $this->assertSame('employee.updated', $result['event']);
        $this->assertSame('Old Name', $result['old_values']['name']);
        $this->assertSame('New Name', $result['new_values']['name']);
    }

    public function test_handles_empty_array(): void
    {
        $result = $this->sanitizer->sanitize([]);

        $this->assertSame([], $result);
    }

    public function test_case_insensitive_matching(): void
    {
        $data = [
            'Password' => 'secret',
            'TOKEN' => 'abc',
        ];

        $result = $this->sanitizer->sanitize($data);

        $this->assertSame('[REDACTED]', $result['Password']);
        $this->assertSame('[REDACTED]', $result['TOKEN']);
    }
}
