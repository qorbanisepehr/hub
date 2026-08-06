<?php

use App\Domains\Settings\Services\SettingsService;
use App\Domains\Settings\Support\Branding;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function settingsStoragePath(): string
{
    return storage_path('app/settings-test-'.uniqid());
}

function deleteDirectory(string $path): void
{
    if (! is_dir($path)) {
        return;
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($files as $file) {
        $file->isDir() ? @rmdir($file->getRealPath()) : @unlink($file->getRealPath());
    }

    @rmdir($path);
}

beforeEach(function () {
    Storage::fake('branding');
    config(['settings.storage_path' => settingsStoragePath()]);
    $this->settingsPath = config('settings.storage_path');
});

afterEach(function () {
    deleteDirectory($this->settingsPath);
});

describe('branding endpoints', function () {
    describe('unauthenticated', function () {
        it('rejects management endpoints', function () {
            $this->putJson('/api/settings/branding', ['name' => 'Test'])->assertStatus(401);
            $this->postJson('/api/settings/branding/logo', ['file' => UploadedFile::fake()->image('logo.png')])->assertStatus(401);
            $this->deleteJson('/api/settings/branding/logo')->assertStatus(401);
            $this->postJson('/api/settings/branding/logotype', ['file' => UploadedFile::fake()->image('logotype.png')])->assertStatus(401);
            $this->deleteJson('/api/settings/branding/logotype')->assertStatus(401);
            $this->postJson('/api/settings/branding/favicon', ['file' => UploadedFile::fake()->create('favicon.png', 100, 'image/png')])->assertStatus(401);
            $this->deleteJson('/api/settings/branding/favicon')->assertStatus(401);
            $this->postJson('/api/settings/branding/og_image', ['file' => UploadedFile::fake()->image('og.png')])->assertStatus(401);
            $this->deleteJson('/api/settings/branding/og_image')->assertStatus(401);
        });
    });

    describe('without branding.manage permission', function () {
        it('forbids management endpoints', function () {
            $user = createUserWithPermissions();

            $this->actingAs($user)
                ->putJson('/api/settings/branding', ['name' => 'Test'])
                ->assertStatus(403);

            $this->actingAs($user)
                ->deleteJson('/api/settings/branding/logo')
                ->assertStatus(403);

            $this->actingAs($user)
                ->deleteJson('/api/settings/branding/favicon')
                ->assertStatus(403);

            $this->actingAs($user)
                ->postJson('/api/settings/branding/og_image', ['file' => UploadedFile::fake()->image('og.png')])
                ->assertStatus(403);
        });
    });

    describe('with branding.manage permission', function () {
        it('updates and persists the brand name, sub-name and colors', function () {
            $user = createUserWithPermissions(['branding.manage']);

            $this->actingAs($user)
                ->putJson('/api/settings/branding', [
                    'name' => 'شرکت نمونه',
                    'sub_name' => 'مدیریت اسناد',
                    'primary_color' => '#ff0000',
                    'secondary_color' => '#00ff00',
                ])
                ->assertStatus(200)
                ->assertJsonPath('data.name', 'شرکت نمونه')
                ->assertJsonPath('data.sub_name', 'مدیریت اسناد')
                ->assertJsonPath('data.primary_color', '#ff0000')
                ->assertJsonPath('data.secondary_color', '#00ff00');

            expect(is_file($this->settingsPath.'/branding.json'))->toBeTrue();

            app(SettingsService::class)->flush();

            expect(Branding::name())->toBe('شرکت نمونه');
            expect(Branding::subName())->toBe('مدیریت اسناد');
            expect(Branding::primaryColor())->toBe('#ff0000');
            expect(Branding::secondaryColor())->toBe('#00ff00');
        });

        it('validates the name length', function () {
            $user = createUserWithPermissions(['branding.manage']);

            $this->actingAs($user)
                ->putJson('/api/settings/branding', [
                    'name' => str_repeat('a', 101),
                ])
                ->assertStatus(422);
        });

        it('rejects invalid hex colors', function () {
            $user = createUserWithPermissions(['branding.manage']);

            $this->actingAs($user)
                ->putJson('/api/settings/branding', [
                    'name' => 'شرکت نمونه',
                    'primary_color' => 'red',
                    'secondary_color' => '#12g345',
                ])
                ->assertStatus(422);
        });

        it('accepts short hex colors', function () {
            $user = createUserWithPermissions(['branding.manage']);

            $this->actingAs($user)
                ->putJson('/api/settings/branding', [
                    'name' => 'شرکت نمونه',
                    'primary_color' => '#f00',
                    'secondary_color' => '#0f0',
                ])
                ->assertStatus(200)
                ->assertJsonPath('data.primary_color', '#f00')
                ->assertJsonPath('data.secondary_color', '#0f0');
        });

        it('uploads a logo and returns its public url', function () {
            $user = createUserWithPermissions(['branding.manage']);
            $file = UploadedFile::fake()->image('logo.png', 120, 120);

            $this->actingAs($user)
                ->postJson('/api/settings/branding/logo', ['file' => $file])
                ->assertStatus(200)
                ->assertJsonPath('data.logo_url', route('settings.branding.logo', ['v' => Storage::disk('branding')->lastModified('logo.png')]));

            Storage::disk('branding')->assertExists('logo.png');
        });

        it('uploads a logotype and returns its public url', function () {
            $user = createUserWithPermissions(['branding.manage']);
            $file = UploadedFile::fake()->image('logotype.png', 300, 60);

            $this->actingAs($user)
                ->postJson('/api/settings/branding/logotype', ['file' => $file])
                ->assertStatus(200)
                ->assertJsonPath('data.logotype_url', route('settings.branding.logotype', ['v' => Storage::disk('branding')->lastModified('logotype.png')]));

            Storage::disk('branding')->assertExists('logotype.png');
        });

        it('injects the svg logo content for synchronous recoloring', function () {
            $user = createUserWithPermissions(['branding.manage']);
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><path fill="#000000" d="M0 0h100v100H0z"/></svg>';

            $this->actingAs($user)
                ->postJson('/api/settings/branding/logo', ['file' => UploadedFile::fake()->createWithContent('logo.svg', $svg)])
                ->assertStatus(200)
                ->assertJsonPath('data.logo_svg', $svg);

            Storage::disk('branding')->assertExists('logo.svg');
        });

        it('keeps the injected svg content null for raster uploads', function () {
            $user = createUserWithPermissions(['branding.manage']);

            $this->actingAs($user)
                ->postJson('/api/settings/branding/logo', ['file' => UploadedFile::fake()->image('logo.png', 120, 120)])
                ->assertStatus(200)
                ->assertJsonPath('data.logo_svg', null);
        });

        it('uploads a favicon and returns its public url', function () {
            $user = createUserWithPermissions(['branding.manage']);
            $file = UploadedFile::fake()->image('favicon.png', 32, 32);

            $this->actingAs($user)
                ->postJson('/api/settings/branding/favicon', ['file' => $file])
                ->assertStatus(200)
                ->assertJsonPath('data.favicon_url', route('settings.branding.favicon', ['v' => Storage::disk('branding')->lastModified('favicon.png')]));

            Storage::disk('branding')->assertExists('favicon.png');
        });

        it('uploads an og image and returns its public url', function () {
            $user = createUserWithPermissions(['branding.manage']);
            $file = UploadedFile::fake()->image('og.png', 1200, 630);

            $this->actingAs($user)
                ->postJson('/api/settings/branding/og_image', ['file' => $file])
                ->assertStatus(200)
                ->assertJsonPath('data.og_image_url', route('settings.branding.og_image', ['v' => Storage::disk('branding')->lastModified('og_image.png')]));

            Storage::disk('branding')->assertExists('og_image.png');
        });

        it('rejects unsupported file types', function () {
            $user = createUserWithPermissions(['branding.manage']);
            $file = UploadedFile::fake()->create('logo.txt', 100);

            $this->actingAs($user)
                ->postJson('/api/settings/branding/logo', ['file' => $file])
                ->assertStatus(422);
        });

        it('replaces a previously uploaded logo', function () {
            $user = createUserWithPermissions(['branding.manage']);

            $this->actingAs($user)
                ->postJson('/api/settings/branding/logo', ['file' => UploadedFile::fake()->image('first.png')])
                ->assertStatus(200);

            $this->actingAs($user)
                ->postJson('/api/settings/branding/logo', ['file' => UploadedFile::fake()->image('second.png')])
                ->assertStatus(200);

            $files = Storage::disk('branding')->files();

            expect($files)->toHaveCount(1);
            expect($files[0])->toBe('logo.png');
        });

        it('deletes the uploaded logo and falls back to the default', function () {
            $user = createUserWithPermissions(['branding.manage']);

            $this->actingAs($user)
                ->postJson('/api/settings/branding/logo', ['file' => UploadedFile::fake()->image('logo.png')])
                ->assertStatus(200);

            $this->actingAs($user)
                ->deleteJson('/api/settings/branding/logo')
                ->assertStatus(200)
                ->assertJsonPath('data.logo_url', null)
                ->assertJsonPath('data.logo_svg', null);

            Storage::disk('branding')->assertMissing('logo.png');
        });

        it('deletes the uploaded favicon and falls back to the default', function () {
            $user = createUserWithPermissions(['branding.manage']);

            $this->actingAs($user)
                ->postJson('/api/settings/branding/favicon', ['file' => UploadedFile::fake()->image('favicon.png', 32, 32)])
                ->assertStatus(200);

            $this->actingAs($user)
                ->deleteJson('/api/settings/branding/favicon')
                ->assertStatus(200)
                ->assertJsonPath('data.favicon_url', null);

            Storage::disk('branding')->assertMissing('favicon.png');
        });

        it('rejects svg og images and ico logos', function () {
            $user = createUserWithPermissions(['branding.manage']);

            $this->actingAs($user)
                ->postJson('/api/settings/branding/og_image', ['file' => UploadedFile::fake()->create('og.svg', 100, 'image/svg+xml')])
                ->assertStatus(422);

            $this->actingAs($user)
                ->postJson('/api/settings/branding/logo', ['file' => UploadedFile::fake()->create('logo.ico', 100, 'image/x-icon')])
                ->assertStatus(422);
        });
    });

    describe('public image serving', function () {
        it('serves the uploaded logo with immutable cache headers', function () {
            $user = createUserWithPermissions(['branding.manage']);
            $file = UploadedFile::fake()->image('logo.png', 120, 120);

            $this->actingAs($user)
                ->postJson('/api/settings/branding/logo', ['file' => $file])
                ->assertStatus(200);

            $response = $this->get('/api/settings/branding/logo');

            $response->assertStatus(200);

            $cacheControl = $response->headers->get('Cache-Control');

            expect($cacheControl)->toContain('public')
                ->toContain('max-age=31536000')
                ->toContain('immutable');

            expect(strlen($response->streamedContent()))->toBeGreaterThan(0);
        });

        it('serves the uploaded favicon with immutable cache headers', function () {
            $user = createUserWithPermissions(['branding.manage']);
            $file = UploadedFile::fake()->image('favicon.png', 32, 32);

            $this->actingAs($user)
                ->postJson('/api/settings/branding/favicon', ['file' => $file])
                ->assertStatus(200);

            $response = $this->get('/api/settings/branding/favicon');

            $response->assertStatus(200);

            $cacheControl = $response->headers->get('Cache-Control');

            expect($cacheControl)->toContain('public')
                ->toContain('max-age=31536000')
                ->toContain('immutable');

            expect(strlen($response->streamedContent()))->toBeGreaterThan(0);
        });

        it('returns 404 when no image has been uploaded', function () {
            $this->get('/api/settings/branding/logo')->assertStatus(404);
            $this->get('/api/settings/branding/logotype')->assertStatus(404);
            $this->get('/api/settings/branding/favicon')->assertStatus(404);
            $this->get('/api/settings/branding/og_image')->assertStatus(404);
        });
    });
});
