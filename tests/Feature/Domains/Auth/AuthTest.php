<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

describe('auth endpoints', function () {
    describe('login (request OTP)', function () {
        it('sends OTP via email', function () {
            $user = User::factory()->create();

            $response = $this->postJson('/api/auth/login', [
                'identifier' => $user->email,
            ]);

            $response->assertStatus(200)
                ->assertJsonStructure(['message', 'destination']);

            expect($user->fresh()->otp_code)->not->toBeNull();
        });

        it('sends OTP via SMS for phone identifier', function () {
            $user = User::factory()->create();

            $this->postJson('/api/auth/login', [
                'identifier' => $user->phone,
            ])->assertStatus(200);
        });

        it('sends OTP for username identifier', function () {
            $user = User::factory()->create();

            $this->postJson('/api/auth/login', [
                'identifier' => $user->username,
            ])->assertStatus(200);
        });

        it('fails with unknown identifier', function () {
            $this->postJson('/api/auth/login', [
                'identifier' => 'unknown@example.com',
            ])->assertStatus(401);
        });

        it('fails when user is locked due to too many failed attempts', function () {
            $user = User::factory()->create();

            foreach (range(1, 5) as $i) {
                RateLimiter::hit('login-attempts:'.$user->id, 60);
            }

            $response = $this->postJson('/api/auth/login', [
                'identifier' => $user->email,
            ]);

            $response->assertStatus(429)
                ->assertJsonStructure(['retry_after']);
        });
    });

    describe('verify OTP', function () {
        it('returns a token with valid OTP', function () {
            $user = User::factory()->create([
                'otp_code' => Hash::make('123456'),
                'otp_expires_at' => now()->addMinutes(5),
            ]);

            $response = $this->postJson('/api/auth/verify-otp', [
                'identifier' => $user->email,
                'code' => '123456',
            ]);

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'user' => ['id', 'name', 'email', 'phone', 'username'],
                    'token',
                ]);

            expect($user->fresh()->otp_code)->toBeNull();
        });

        it('increments attempt count on invalid code', function () {
            $user = User::factory()->create([
                'otp_code' => Hash::make('123456'),
                'otp_expires_at' => now()->addMinutes(5),
            ]);

            $this->postJson('/api/auth/verify-otp', [
                'identifier' => $user->email,
                'code' => '000000',
            ]);

            expect(RateLimiter::attempts('login-attempts:'.$user->id))->toBe(1);
        });

        it('locks user after 5 failed attempts', function () {
            $user = User::factory()->create([
                'otp_code' => Hash::make('123456'),
                'otp_expires_at' => now()->addMinutes(5),
            ]);

            foreach (range(1, 5) as $i) {
                $this->postJson('/api/auth/verify-otp', [
                    'identifier' => $user->email,
                    'code' => 'wrong'.$i,
                ]);
            }

            $response = $this->postJson('/api/auth/verify-otp', [
                'identifier' => $user->email,
                'code' => '123456',
            ]);

            $response->assertStatus(429);
        });

        it('fails with invalid OTP', function () {
            $user = User::factory()->create([
                'otp_code' => Hash::make('123456'),
                'otp_expires_at' => now()->addMinutes(5),
            ]);

            $this->postJson('/api/auth/verify-otp', [
                'identifier' => $user->email,
                'code' => '000000',
            ])->assertStatus(422);
        });

        it('fails with expired OTP', function () {
            $user = User::factory()->create([
                'otp_code' => Hash::make('123456'),
                'otp_expires_at' => now()->subMinute(),
            ]);

            $this->postJson('/api/auth/verify-otp', [
                'identifier' => $user->email,
                'code' => '123456',
            ])->assertStatus(422);
        });

        it('clears attempts on successful verification', function () {
            $user = User::factory()->create([
                'otp_code' => Hash::make('123456'),
                'otp_expires_at' => now()->addMinutes(5),
            ]);

            RateLimiter::hit('login-attempts:'.$user->id, 60);
            RateLimiter::hit('login-attempts:'.$user->id, 60);

            $this->postJson('/api/auth/verify-otp', [
                'identifier' => $user->email,
                'code' => '123456',
            ]);

            expect(RateLimiter::attempts('login-attempts:'.$user->id))->toBe(0);
        });
    });

    describe('login with password', function () {
        it('returns a token with valid credentials', function () {
            $user = User::factory()->create();

            $this->postJson('/api/auth/login-with-password', [
                'identifier' => $user->email,
                'password' => 'password',
            ])->assertStatus(200)
                ->assertJsonStructure(['user', 'token']);
        });

        it('accepts phone as identifier', function () {
            $user = User::factory()->create();

            $this->postJson('/api/auth/login-with-password', [
                'identifier' => $user->phone,
                'password' => 'password',
            ])->assertStatus(200);
        });

        it('accepts username as identifier', function () {
            $user = User::factory()->create();

            $this->postJson('/api/auth/login-with-password', [
                'identifier' => $user->username,
                'password' => 'password',
            ])->assertStatus(200);
        });

        it('fails with wrong password', function () {
            $user = User::factory()->create();

            $this->postJson('/api/auth/login-with-password', [
                'identifier' => $user->email,
                'password' => 'wrong-password',
            ])->assertStatus(401);
        });

        it('increments attempt count on wrong password', function () {
            $user = User::factory()->create();

            $this->postJson('/api/auth/login-with-password', [
                'identifier' => $user->email,
                'password' => 'wrong-password',
            ]);

            expect(RateLimiter::attempts('login-attempts:'.$user->id))->toBe(1);
        });

        it('fails when user is locked', function () {
            $user = User::factory()->create();

            foreach (range(1, 5) as $i) {
                RateLimiter::hit('login-attempts:'.$user->id, 60);
            }

            $response = $this->postJson('/api/auth/login-with-password', [
                'identifier' => $user->email,
                'password' => 'password',
            ]);

            $response->assertStatus(429);
        });

        it('clears attempts on successful login', function () {
            $user = User::factory()->create();

            RateLimiter::hit('login-attempts:'.$user->id, 60);
            RateLimiter::hit('login-attempts:'.$user->id, 60);

            $this->postJson('/api/auth/login-with-password', [
                'identifier' => $user->email,
                'password' => 'password',
            ]);

            expect(RateLimiter::attempts('login-attempts:'.$user->id))->toBe(0);
        });

        it('fails with unknown identifier', function () {
            $this->postJson('/api/auth/login-with-password', [
                'identifier' => 'unknown@example.com',
                'password' => 'password',
            ])->assertStatus(401);
        });
    });

    describe('logout', function () {
        it('revokes the current token', function () {
            $user = User::factory()->create();
            $token = $user->createToken('test-token')->plainTextToken;

            $this->withToken($token)
                ->postJson('/api/auth/logout')
                ->assertStatus(200)
                ->assertJson(['message' => __('auth.logout')]);

            expect($user->tokens()->count())->toBe(0);
        });

        it('fails without authentication', function () {
            $this->postJson('/api/auth/logout')->assertStatus(401);
        });
    });

    describe('me', function () {
        it('returns the authenticated user', function () {
            $user = User::factory()->create();
            $token = $user->createToken('test-token')->plainTextToken;

            $this->withToken($token)
                ->getJson('/api/auth/me')
                ->assertStatus(200)
                ->assertJson([
                    'data' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'username' => $user->username,
                    ],
                ]);
        });

        it('fails without authentication', function () {
            $this->getJson('/api/auth/me')->assertStatus(401);
        });
    });
});
