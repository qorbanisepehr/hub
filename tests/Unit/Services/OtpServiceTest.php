<?php

namespace Tests\Unit\Services;

use App\Enums\OtpContext;
use App\Models\User;
use App\Services\OtpService;
use App\Support\Mail\OtpMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OtpServiceTest extends TestCase
{
    use RefreshDatabase;

    private OtpService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OtpService::class);
    }

    public function test_email_send_queues_otp_mail_to_the_recipient(): void
    {
        $user = User::factory()->create();
        Mail::fake();

        $code = $this->service->send($user, 'email', OtpContext::Login, 'jane@example.com');

        Mail::assertQueued(
            OtpMail::class,
            fn (OtpMail $mail): bool => $mail->hasTo('jane@example.com'),
        );

        $this->assertSame(6, strlen($code));
    }

    public function test_mobile_send_does_not_queue_mail(): void
    {
        $user = User::factory()->create();
        Mail::fake();

        $this->service->send($user, 'mobile', OtpContext::Login);

        Mail::assertNothingQueued();
    }

    public function test_email_send_without_recipient_does_not_queue_mail(): void
    {
        $user = User::factory()->create();
        Mail::fake();

        $this->service->send($user, 'email', OtpContext::Login);

        Mail::assertNothingQueued();
    }

    public function test_email_channel_uses_separate_ttl(): void
    {
        $user = User::factory()->create();
        Mail::fake();

        $code = $this->service->send($user, 'email', OtpContext::Login, 'jane@example.com');

        $data = Cache::get("otp:login:user:{$user->id}:email");
        $this->assertNotNull($data);
        $this->assertTrue(Hash::check($code, $data['hash']));
        $this->assertNotSame($code, $data['hash'], 'stored value must be the hash, not plaintext');

        $emailTtl = (int) config('otp.ttl_email', 300);
        $expected = now()->addSeconds($emailTtl)->timestamp;
        $this->assertSame($expected, (int) $data['expires_at']);
    }

    public function test_generated_code_is_real_random_six_digits(): void
    {
        $user = User::factory()->create();
        Mail::fake();

        $codes = [];
        for ($i = 0; $i < 5; $i++) {
            $codes[] = $this->service->send($user, 'email', OtpContext::Login, 'jane@example.com');
            $this->service->verify($user, 'email', OtpContext::Login, $codes[$i]);
        }

        $this->assertCount(5, array_unique($codes), 'codes should not all be identical');
        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
            $this->assertNotSame('123456', $code);
        }
    }
}
