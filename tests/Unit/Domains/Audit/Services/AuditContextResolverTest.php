<?php

namespace Tests\Unit\Domains\Audit\Services;

use App\Domains\Audit\Services\AuditContextResolver;
use App\Domains\Authorization\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditContextResolverTest extends TestCase
{
    use RefreshDatabase;

    private AuditContextResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new AuditContextResolver;
    }

    public function test_resolve_without_actor_returns_null_fields(): void
    {
        $context = $this->resolver->resolve();

        $this->assertNull($context->actorId);
        $this->assertNull($context->actorType);
        $this->assertNull($context->actorRoleId);
        $this->assertNull($context->actorRoleName);
    }

    public function test_resolve_with_user_actor_populates_actor_fields(): void
    {
        $user = User::factory()->create();
        $role = Role::create([
            'name' => 'admin',
            'display_name' => 'Admin',
            'is_active' => true,
        ]);
        $user->assignRole($role->id, true);

        $context = $this->resolver->resolve($user);

        $this->assertSame($user->id, $context->actorId);
        $this->assertSame('user', $context->actorType);
        $this->assertSame($role->id, $context->actorRoleId);
        $this->assertSame('admin', $context->actorRoleName);
    }

    public function test_resolve_with_user_without_role_has_null_role(): void
    {
        $user = User::factory()->create();

        $context = $this->resolver->resolve($user);

        $this->assertSame($user->id, $context->actorId);
        $this->assertSame('user', $context->actorType);
        $this->assertNull($context->actorRoleId);
        $this->assertNull($context->actorRoleName);
    }

    public function test_resolve_with_non_user_actor_returns_null_actor(): void
    {
        $actor = new class implements Authenticatable
        {
            public function getAuthIdentifierName(): string
            {
                return 'id';
            }

            public function getAuthIdentifier(): int
            {
                return 1;
            }

            public function getAuthPassword(): string
            {
                return '';
            }

            public function getRememberToken(): ?string
            {
                return null;
            }

            public function setRememberToken($value): void {}

            public function getRememberTokenName(): string
            {
                return 'remember_token';
            }

            public function getAuthPasswordName(): string
            {
                return 'password';
            }
        };

        $context = $this->resolver->resolve($actor);

        $this->assertNull($context->actorId);
        $this->assertNull($context->actorType);
    }
}
