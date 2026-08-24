<?php

namespace Database\Factories;

use App\Domains\Audit\Models\AuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'event_id' => fake()->uuid(),
            'event' => 'test.event',
            'category' => 'test',
            'actor_type' => 'user',
            'actor_id' => null,
            'actor_role_id' => null,
            'actor_role_name' => null,
            'subject_type' => null,
            'subject_id' => null,
            'subject_snapshot' => null,
            'description' => fake()->sentence(),
            'old_values' => null,
            'new_values' => null,
            'metadata' => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'url' => fake()->url(),
            'method' => 'GET',
            'request_id' => null,
            'trace_id' => null,
            'created_at' => now(),
        ];
    }

    public function forEmployee(): static
    {
        return $this->state(fn () => [
            'event' => 'employee.created',
            'category' => 'employee',
            'subject_type' => 'employee',
        ]);
    }

    public function forDocument(): static
    {
        return $this->state(fn () => [
            'event' => 'document.uploaded',
            'category' => 'document',
            'subject_type' => 'document',
        ]);
    }

    public function forAuth(): static
    {
        return $this->state(fn () => [
            'event' => 'auth.login.success',
            'category' => 'auth',
            'subject_type' => null,
        ]);
    }
}
