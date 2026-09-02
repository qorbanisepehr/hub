<?php

namespace Tests\Unit\Domains\Auth\Services;

use App\Domains\Auth\Services\AuthService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuthService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AuthService;
    }

    public function test_resolve_user_by_email(): void
    {
        $user = User::factory()->create(['email' => 'jane@example.com']);

        $this->assertSame($user->id, $this->service->resolveUser('jane@example.com')?->id);
    }

    public function test_resolve_user_by_phone(): void
    {
        $user = User::factory()->create(['phone' => '+989121112233']);

        $this->assertSame($user->id, $this->service->resolveUser('+989121112233')?->id);
    }

    public function test_resolve_user_by_username(): void
    {
        $user = User::factory()->create(['username' => 'jane']);

        $this->assertSame($user->id, $this->service->resolveUser('jane')?->id);
    }

    public function test_resolve_user_returns_null_for_unknown_identifier(): void
    {
        $this->assertNull($this->service->resolveUser('404'));
    }

    public function test_channel_for_email_identifier_is_email(): void
    {
        $this->assertSame('email', $this->service->channelFor('jane@example.com'));
    }

    public function test_channel_for_phone_identifier_is_mobile(): void
    {
        $this->assertSame('mobile', $this->service->channelFor('+989121112233'));
    }

    public function test_destination_masks_email_address(): void
    {
        $user = User::factory()->create(['email' => 'jane@example.com']);

        $this->assertSame('j***e@example.com', $this->service->destination('jane@example.com', $user));
    }

    public function test_destination_masks_phone_number(): void
    {
        $user = User::factory()->create(['phone' => '+989121112233']);

        $this->assertSame('+98***112233', $this->service->destination('+989121112233', $user));
    }

    public function test_destination_falls_back_to_email_when_user_has_no_phone(): void
    {
        $user = User::factory()->create(['email' => 'jane@example.com', 'phone' => null]);

        $this->assertSame('jane@example.com', $this->service->destination('jane', $user));
    }

    public function test_lockout_detected_after_reaching_attempt_limit(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($this->service->isLocked($user));
        $this->assertSame(0, $this->service->lockoutSeconds($user));

        foreach (range(1, config('rate-limits.auth-attempts.limit', 5)) as $attempt) {
            $this->service->hitFailedAttempt($user);
        }

        $this->assertTrue($this->service->isLocked($user));
        $this->assertGreaterThan(0, $this->service->lockoutSeconds($user));
    }

    public function test_clear_failed_attempts_unlocks_user(): void
    {
        $user = User::factory()->create();

        $this->service->hitFailedAttempt($user);
        $this->service->hitFailedAttempt($user);
        $this->service->clearFailedAttempts($user);

        $this->assertSame(0, RateLimiter::attempts('login-attempts:'.$user->id));
        $this->assertFalse($this->service->isLocked($user));
    }

    public function test_create_token_returns_plain_text_token(): void
    {
        $user = User::factory()->create();

        $token = $this->service->createToken($user, '127.0.0.1', 'TestAgent/1.0');

        $this->assertNotEmpty($token);
        $this->assertSame(1, $user->tokens()->count());
    }
}
