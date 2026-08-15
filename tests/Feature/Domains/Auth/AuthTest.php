<?php

use App\Domains\Authorization\Models\Permission;
use App\Domains\Authorization\Models\PermissionGroup;
use App\Domains\Authorization\Models\Role;
use App\Domains\Employee\Models\Employee;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

function stateful(): array
{
    return ['Referer' => 'http://localhost'];
}

function seedLoginOtp(User $user, string $code = '123456', bool $expired = false): void
{
    $expiresAt = $expired ? now()->subMinute() : now()->addMinutes(5);

    Cache::put(
        "otp:login:user:{$user->id}:email",
        [
            'hash' => Hash::make($code),
            'expires_at' => $expiresAt->timestamp,
        ],
        $expiresAt,
    );
}

describe('auth endpoints', function () {
    describe('login (request OTP)', function () {
        it('sends OTP via email', function () {
            $user = User::factory()->create();

            $response = $this->postJson('/api/auth/login', [
                'identifier' => $user->email,
            ]);

            $response->assertStatus(200)
                ->assertJsonStructure(['message', 'destination']);

            expect(Cache::has("otp:login:user:{$user->id}:email"))->toBeTrue();
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

        it('fails when user is inactive', function () {
            $user = User::factory()->create(['is_active' => false]);

            $this->postJson('/api/auth/login', [
                'identifier' => $user->email,
            ])->assertStatus(403)
                ->assertJsonPath('message', __('auth.inactive'));
        });

        it('fails when user is locked due to too many failed attempts', function () {
            $user = User::factory()->create();

            foreach (range(1, 5) as $i) {
                RateLimiter::hit("otp-attempts:login:user:{$user->id}:email", 60);
            }

            $response = $this->postJson('/api/auth/login', [
                'identifier' => $user->email,
            ]);

            $response->assertStatus(429)
                ->assertJsonStructure(['retry_after']);
        });

        it('returns 429 for a locked user even when a code is still valid', function () {
            $user = User::factory()->create();
            seedLoginOtp($user);

            foreach (range(1, 5) as $i) {
                RateLimiter::hit("otp-attempts:login:user:{$user->id}:email", 60);
            }

            $response = $this->postJson('/api/auth/login', [
                'identifier' => $user->email,
            ]);

            $response->assertStatus(429)
                ->assertJsonPath('message', __('auth.locked', ['seconds' => $response->json('retry_after')]));
        });
    });

    describe('verify OTP', function () {
        it('authenticates with valid OTP (stateful)', function () {
            $user = User::factory()->create();
            seedLoginOtp($user);

            $response = $this->withHeaders(stateful())
                ->postJson('/api/auth/verify-otp', [
                    'identifier' => $user->email,
                    'code' => '123456',
                ]);

            $response->assertStatus(200)
                ->assertJsonStructure(['user' => ['id', 'name', 'email', 'phone', 'username']])
                ->assertJsonMissing(['token' => true]);

            expect(Cache::has("otp:login:user:{$user->id}:email"))->toBeFalse();
            expect(auth()->check())->toBeTrue();
        });

        it('authenticates with valid OTP (stateless, returns token)', function () {
            $user = User::factory()->create();
            seedLoginOtp($user);

            $response = $this->postJson('/api/auth/verify-otp', [
                'identifier' => $user->email,
                'code' => '123456',
            ]);

            $response->assertStatus(200)
                ->assertJsonStructure(['user', 'token']);

            expect(Cache::has("otp:login:user:{$user->id}:email"))->toBeFalse();
        });

        it('increments attempt count on invalid code', function () {
            $user = User::factory()->create();
            seedLoginOtp($user);

            $this->postJson('/api/auth/verify-otp', [
                'identifier' => $user->email,
                'code' => '000000',
            ]);

            expect(RateLimiter::attempts("otp-attempts:login:user:{$user->id}:email"))->toBe(1);
        });

        it('locks user after 5 failed attempts', function () {
            $user = User::factory()->create();
            seedLoginOtp($user);

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
            $user = User::factory()->create();
            seedLoginOtp($user);

            $this->postJson('/api/auth/verify-otp', [
                'identifier' => $user->email,
                'code' => '000000',
            ])->assertStatus(422);
        });

        it('fails when user is inactive', function () {
            $user = User::factory()->create(['is_active' => false]);
            seedLoginOtp($user);

            $this->postJson('/api/auth/verify-otp', [
                'identifier' => $user->email,
                'code' => '123456',
            ])->assertStatus(403)
                ->assertJsonPath('message', __('auth.inactive'));
        });

        it('fails with expired OTP', function () {
            $user = User::factory()->create();
            seedLoginOtp($user, expired: true);

            $this->postJson('/api/auth/verify-otp', [
                'identifier' => $user->email,
                'code' => '123456',
            ])->assertStatus(422);
        });

        it('clears attempts on successful verification', function () {
            $user = User::factory()->create();
            seedLoginOtp($user);

            RateLimiter::hit("otp-attempts:login:user:{$user->id}:email", 60);
            RateLimiter::hit("otp-attempts:login:user:{$user->id}:email", 60);

            $this->postJson('/api/auth/verify-otp', [
                'identifier' => $user->email,
                'code' => '123456',
            ]);

            expect(RateLimiter::attempts("otp-attempts:login:user:{$user->id}:email"))->toBe(0);
        });
    });

    describe('login with password', function () {
        it('authenticates with valid credentials (stateful)', function () {
            $user = User::factory()->create();

            $this->withHeaders(stateful())
                ->postJson('/api/auth/login-with-password', [
                    'identifier' => $user->email,
                    'password' => 'password',
                ])->assertStatus(200)
                ->assertJsonStructure(['user'])
                ->assertJsonMissing(['token' => true]);

            expect(auth()->check())->toBeTrue();
        });

        it('authenticates with valid credentials (stateless, returns token)', function () {
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

        it('fails when user is inactive', function () {
            $user = User::factory()->create(['is_active' => false]);

            $this->postJson('/api/auth/login-with-password', [
                'identifier' => $user->email,
                'password' => 'password',
            ])->assertStatus(403)
                ->assertJsonPath('message', __('auth.inactive'));
        });
    });

    describe('logout', function () {
        it('ends the session (stateful)', function () {
            $user = User::factory()->create();
            $sessionName = config('session.cookie');

            $login = $this->withHeaders(stateful())
                ->postJson('/api/auth/login-with-password', [
                    'identifier' => $user->email,
                    'password' => 'password',
                ])->assertStatus(200);

            $sessionCookie = collect($login->headers->getCookies())
                ->firstWhere('name', $sessionName)?->getValue() ?? '';

            $logout = $this->withUnencryptedCookies([$sessionName => $sessionCookie])
                ->withHeaders(stateful())
                ->postJson('/api/auth/logout')
                ->assertStatus(200)
                ->assertJson(['message' => __('auth.logout')]);

            $newSessionCookie = collect($logout->headers->getCookies())
                ->firstWhere('name', $sessionName)?->getValue() ?? '';

            $this->withUnencryptedCookies([$sessionName => $newSessionCookie])
                ->withHeaders(stateful())
                ->getJson('/api/auth/me')
                ->assertStatus(401);
        });

        it('fails without authentication', function () {
            $this->postJson('/api/auth/logout')->assertStatus(401);
        });
    });

    describe('me', function () {
        it('returns the authenticated user (stateful)', function () {
            $user = User::factory()->create();

            $this->withHeaders(stateful())
                ->postJson('/api/auth/login-with-password', [
                    'identifier' => $user->email,
                    'password' => 'password',
                ])->assertStatus(200);

            $this->withHeaders(stateful())
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

        it('includes linked employee data', function () {
            $user = User::factory()->create();
            $employee = Employee::factory()->create();
            $user->employee()->save($employee);

            $this->actingAs($user)
                ->getJson('/api/auth/me')
                ->assertStatus(200)
                ->assertJsonPath('data.employee.id', $employee->id)
                ->assertJsonPath('data.employee.first_name', $employee->first_name)
                ->assertJsonPath('data.employee.last_name', $employee->last_name)
                ->assertJsonPath('data.employee.personnel_code', $employee->personnel_code);
        });
    });

    describe('authorization', function () {
        it('returns the active role and allowed permissions map', function () {
            $user = createUserWithPermissions(['user.view', 'role.view']);
            $role = $user->activeRole;

            $this->actingAs($user)
                ->getJson('/api/auth/me/authorization')
                ->assertStatus(200)
                ->assertJsonPath('data.role.id', $role->id)
                ->assertJsonPath('data.role.name', $role->name)
                ->assertJson([
                    'data' => [
                        'permissions' => [
                            'user.view' => ['allowed' => true],
                            'role.view' => ['allowed' => true],
                        ],
                    ],
                ]);
        });

        it('applies deny precedence over allow rules', function () {
            $user = User::factory()->create();
            $group = PermissionGroup::firstOrCreate(['slug' => 'test'], ['name' => 'Test Group']);
            $permission = Permission::firstOrCreate(
                ['name' => 'user.update'],
                ['display_name' => 'Update user', 'group_id' => $group->id],
            );
            $role = Role::create(['name' => 'deny-test', 'display_name' => 'Deny Test', 'is_active' => true]);
            $role->permissions()->attach($permission);
            $role->denyPermission($permission->id);
            $user->assignRole($role->id, true);

            $this->actingAs($user)
                ->getJson('/api/auth/me/authorization')
                ->assertStatus(200)
                ->assertJson([
                    'data' => [
                        'permissions' => [
                            'user.update' => ['allowed' => false],
                        ],
                    ],
                ]);
        });

        it('returns a null role and empty map for a user without roles', function () {
            $user = User::factory()->create();

            $this->actingAs($user)
                ->getJson('/api/auth/me/authorization')
                ->assertStatus(200)
                ->assertJsonPath('data.role', null)
                ->assertJsonPath('data.permissions', []);
        });

        it('requires authentication', function () {
            $this->getJson('/api/auth/me/authorization')->assertStatus(401);
        });
    });

    describe('profile', function () {
        it('updates profile fields', function () {
            $user = User::factory()->create();

            $this->actingAs($user)
                ->putJson('/api/auth/profile', [
                    'name' => 'New Name',
                    'email' => 'new@example.com',
                ])
                ->assertStatus(200)
                ->assertJsonPath('data.name', 'New Name')
                ->assertJsonPath('data.email', 'new@example.com');
        });

        it('validates unique email on profile update', function () {
            $user = User::factory()->create();
            User::factory()->create(['email' => 'taken@example.com']);

            $this->actingAs($user)
                ->putJson('/api/auth/profile', [
                    'email' => 'taken@example.com',
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
        });

        it('allows keeping current email on profile update', function () {
            $user = User::factory()->create(['email' => 'current@example.com']);

            $this->actingAs($user)
                ->putJson('/api/auth/profile', [
                    'email' => 'current@example.com',
                    'name' => 'Updated Name',
                ])
                ->assertStatus(200);
        });

        it('fails without authentication', function () {
            $this->putJson('/api/auth/profile', ['name' => 'Test'])->assertStatus(401);
        });
    });

    describe('avatar', function () {
        it('uploads an avatar', function () {
            Storage::fake('avatars');
            $user = User::factory()->create();
            $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);

            $this->actingAs($user)
                ->postJson('/api/auth/avatar', ['avatar' => $file])
                ->assertStatus(200)
                ->assertJsonPath('data.id', $user->id);

            Storage::disk('avatars')->assertExists($user->getAvatarStoragePath());
        });

        it('replaces existing avatar on new upload', function () {
            Storage::fake('avatars');
            $user = User::factory()->create();
            $file1 = UploadedFile::fake()->image('avatar1.jpg', 200, 200);
            $file2 = UploadedFile::fake()->image('avatar2.jpg', 200, 200);

            $this->actingAs($user)
                ->postJson('/api/auth/avatar', ['avatar' => $file1])
                ->assertStatus(200);

            $oldPath = $user->fresh()->getAvatarFullPath();

            $this->actingAs($user)
                ->postJson('/api/auth/avatar', ['avatar' => $file2])
                ->assertStatus(200);

            Storage::disk('avatars')->assertMissing($oldPath);
        });

        it('rejects non-image files', function () {
            Storage::fake('avatars');
            $user = User::factory()->create();
            $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

            $this->actingAs($user)
                ->postJson('/api/auth/avatar', ['avatar' => $file])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['avatar']);
        });

        it('rejects oversized avatars', function () {
            Storage::fake('avatars');
            $user = User::factory()->create();
            $file = UploadedFile::fake()->image('avatar.jpg', 200, 200)->size(3000);

            $this->actingAs($user)
                ->postJson('/api/auth/avatar', ['avatar' => $file])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['avatar']);
        });

        it('deletes avatar', function () {
            Storage::fake('avatars');
            $user = User::factory()->create();
            $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);

            $this->actingAs($user)
                ->postJson('/api/auth/avatar', ['avatar' => $file])
                ->assertStatus(200);

            $avatarPath = $user->fresh()->getAvatarFullPath();

            $this->actingAs($user)
                ->deleteJson('/api/auth/avatar')
                ->assertStatus(200)
                ->assertJsonPath('data.avatar_url', null);

            Storage::disk('avatars')->assertMissing($avatarPath);
            expect($user->fresh()->avatar_url)->toBeNull();
        });

        it('serves avatar via signed url', function () {
            Storage::fake('avatars');
            $user = User::factory()->create();
            $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);

            $this->actingAs($user)
                ->postJson('/api/auth/avatar', ['avatar' => $file])
                ->assertStatus(200);

            $signedUrl = URL::temporarySignedRoute(
                'auth.avatar.serve',
                now()->addMinutes(5),
                ['user' => $user->id],
            );

            $path = parse_url($signedUrl, PHP_URL_PATH);

            $this->get($path)->assertStatus(200);
        });

        it('fails without authentication', function () {
            $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);
            $this->postJson('/api/auth/avatar', ['avatar' => $file])->assertStatus(401);
        });
    });
});
