<?php

namespace Tests\Unit\Domains\Reporting;

use App\Domains\Authorization\Models\Role;
use App\Domains\Reporting\Reports\UnifiedInbox\SenderIdentityResolver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SenderIdentityResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_user_by_exact_username_match(): void
    {
        $user = User::factory()->create(['username' => 'r.moradi']);

        $resolved = (new SenderIdentityResolver)->resolve('r.moradi');

        $this->assertNotNull($resolved);
        $this->assertSame($user->id, $resolved->id);
    }

    public function test_match_is_case_insensitive(): void
    {
        User::factory()->create(['username' => 'R.Moradi']);

        $resolver = new SenderIdentityResolver;

        $first = $resolver->resolve('r.moradi');
        $second = $resolver->resolve('R.MORADI');

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertSame($first->id, $second->id);
    }

    public function test_returns_null_for_blank_or_unknown_usernames(): void
    {
        $resolver = new SenderIdentityResolver;

        $this->assertNull($resolver->resolve(null));
        $this->assertNull($resolver->resolve(''));
        $this->assertNull($resolver->resolve('   '));
        $this->assertNull($resolver->resolve('ghost-user'));
    }

    public function test_exposes_org_role_of_the_active_role(): void
    {
        $user = User::factory()->create();

        $role = Role::create([
            'name' => 'test-role-'.uniqid(),
            'display_name' => 'مدیر منابع انسانی',
            'is_active' => true,
        ]);
        $user->assignRole($role->id, true);

        $this->assertSame('مدیر منابع انسانی', (new SenderIdentityResolver)->orgRole($user->fresh()));
    }
}
